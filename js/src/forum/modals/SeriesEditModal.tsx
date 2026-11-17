import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { removeCover, saveSeries, uploadCover } from '../../common/api';
import type { SeriesCard } from '../../common/api';
import Cover from '../components/Cover';
import { t } from '../util';

interface Attrs extends IFormModalAttrs {
  series?: SeriesCard & { description?: string | null };
  onsave?: (card: SeriesCard) => void;
}

/** Start a series, or edit one: title, description, status and cover. */
export default class SeriesEditModal extends FormModal<Attrs> {
  name!: Stream<string>;
  description!: Stream<string>;
  status!: Stream<string>;
  coverUrl: string | null = null;
  file: File | null = null;
  dropCover = false;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    const series = this.attrs.series;
    this.name = Stream(series?.title || '');
    this.description = Stream(series?.description || '');
    this.status = Stream<string>(series?.status || 'ongoing');
    this.coverUrl = series?.coverUrl || null;
  }

  className() {
    return 'AnthologyEditModal Modal--small';
  }

  title() {
    const series = this.attrs.series;
    return series ? t('edit_modal.edit_title', { title: series.title }) : t('edit_modal.create_title');
  }

  content(): Mithril.Children {
    const editing = !!this.attrs.series;
    const statuses: Record<string, string> = {
      ongoing: extractText(t('status.ongoing')),
      complete: extractText(t('status.complete')),
    };

    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label for="anthology-title">{t('edit_modal.title')}</label>
            <input id="anthology-title" className="FormControl" name="title" maxlength={200} bidi={this.name} required />
          </div>
          <div className="Form-group">
            <label for="anthology-description">{t('edit_modal.description')}</label>
            <div className="helpText">{t('edit_modal.description_help')}</div>
            <textarea id="anthology-description" className="FormControl" name="description" rows={5} maxlength={5000} bidi={this.description} />
          </div>
          {editing ? (
            <div className="Form-group">
              <label>{t('edit_modal.status')}</label>
              <Select options={statuses} value={this.status()} onchange={this.status} />
            </div>
          ) : null}
          <div className="Form-group AnthologyEditModal-cover">
            <label for="anthology-cover">{t('edit_modal.cover')}</label>
            <div className="helpText">{t('edit_modal.cover_help')}</div>
            <div className="AnthologyEditModal-coverRow">
              <Cover url={this.dropCover ? null : this.coverUrl} title={this.name()} className="AnthologyEditModal-preview" />
              <div className="AnthologyEditModal-coverActions">
                <input
                  id="anthology-cover"
                  className="FormControl"
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  onchange={(e: Event) => this.pick((e.target as HTMLInputElement).files?.[0] || null)}
                />
                {this.coverUrl && !this.dropCover ? (
                  <Button className="Button Button--link" icon="fas fa-xmark" onclick={() => this.clearCover()}>
                    {t('edit_modal.cover_remove')}
                  </Button>
                ) : null}
              </div>
            </div>
          </div>
          <div className="Form-group">
            <Button className="Button Button--primary Button--block" type="submit" loading={this.loading}>
              {editing ? t('edit_modal.save') : t('edit_modal.create')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  pick(file: File | null) {
    if (this.coverUrl && this.file) URL.revokeObjectURL(this.coverUrl);
    this.file = file;
    this.dropCover = false;
    this.coverUrl = file ? URL.createObjectURL(file) : this.attrs.series?.coverUrl || null;
  }

  clearCover() {
    this.file = null;
    this.dropCover = true;
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    this.loading = true;
    const series = this.attrs.series;
    const data: Record<string, unknown> = { title: this.name(), description: this.description() };
    if (series) data.status = this.status();

    saveSeries(series?.id || null, data)
      .then(async ({ data: card }) => {
        if (this.file) card.coverUrl = (await uploadCover(card.id, this.file)).data.coverUrl;
        else if (this.dropCover && series?.coverUrl) card.coverUrl = (await removeCover(card.id)).data.coverUrl;
        this.hide();
        this.attrs.onsave?.(card);
      })
      .catch((error: any) => {
        this.loading = false;
        this.onerror(error);
      });
  }
}
