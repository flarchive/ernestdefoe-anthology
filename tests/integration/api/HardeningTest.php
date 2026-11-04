<?php

namespace Ernestdefoe\Anthology\Tests\integration\api;

use Ernestdefoe\Anthology\Tests\integration\AnthologyTestCase;
use Carbon\Carbon;
use Flarum\Group\Group;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

class HardeningTest extends AnthologyTestCase
{
    #[Test]
    public function an_owner_without_the_create_permission_can_no_longer_edit(): void
    {
        // Before any request: the test app keeps an actor's permissions between requests.
        $this->database()->table('group_permission')->where('permission', 'anthology.create')->where('group_id', Group::MEMBER_ID)->delete();
        $id = $this->database()->table('anthology_series')->insertGetId(['user_id' => 2, 'title' => 'Mine', 'slug' => 'mine', 'status' => 'ongoing', 'last_chapter_at' => Carbon::now(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        $this->database()->table('anthology_chapters')->insert(['discussion_id' => 1, 'series_id' => $id, 'position' => 1, 'created_at' => Carbon::now()]);

        [$status] = $this->json('PATCH', '/api/anthology/series/'.$id, 2, ['data' => ['title' => 'Renamed']]);
        $this->assertSame(403, $status);
        // Taking their own discussion out of a series still works.
        [$status] = $this->json('DELETE', '/api/anthology/chapters/1', 2);
        $this->assertSame(200, $status);
    }

    #[Test]
    public function a_cover_claiming_huge_dimensions_is_refused_before_decoding(): void
    {
        $series = $this->series();
        $path = tempnam(sys_get_temp_dir(), 'ant');
        file_put_contents($path, $this->bombPng(20000, 20000));
        $file = new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, 'cover.png', 'image/png');

        $request = $this->request('POST', '/api/anthology/series/'.$series['id'].'/cover', ['authenticatedAs' => 2])->withUploadedFiles(['cover' => $file]);
        $response = $this->send($request);

        $this->assertSame(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertLessThan(5 * 1024 * 1024, filesize($path));
    }

    #[Test]
    public function members_cannot_create_series_without_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->series("Series $i");
        }
        [$status] = $this->json('POST', '/api/anthology/series', 2, ['data' => ['title' => 'One too many']]);
        $this->assertSame(429, $status);

        // Moderators aren't limited.
        for ($i = 0; $i < 11; $i++) {
            $this->series("Mod $i", 4);
        }
    }

    /** A valid grey PNG of the given size, deflated so it is small on disk. */
    private function bombPng(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $row = str_repeat("\0", $width + 1);
        $idat = '';
        for ($y = 0; $y < $height; $y++) {
            $idat .= deflate_add($deflate, $row, ZLIB_NO_FLUSH);
        }
        $idat .= deflate_add($deflate, '', ZLIB_FINISH);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0)).$chunk('IDAT', $idat).$chunk('IEND', '');
    }
}
