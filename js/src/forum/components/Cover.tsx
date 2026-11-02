import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';

/** A series cover, or a plain stand-in with the title's first letter. */
export default class Cover extends Component<{ url: string | null; title: string; className?: string }> {
  view(): Mithril.Children {
    const { url, title, className } = this.attrs;

    return url ? (
      <img className={'AnthologyCover ' + (className || '')} src={url} alt="" loading="lazy" />
    ) : (
      <span className={'AnthologyCover AnthologyCover--blank ' + (className || '')} aria-hidden="true">
        <Icon name="fas fa-book-bookmark" />
        <span className="AnthologyCover-letter">{(title || '?').trim().charAt(0).toUpperCase()}</span>
      </span>
    );
  }
}
