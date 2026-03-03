import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
    };

    navigate(event) {
        if (!this.hasUrlValue || this.shouldIgnore(event)) {
            return;
        }

        window.location.assign(this.urlValue);
    }

    shouldIgnore(event) {
        if (event.defaultPrevented) {
            return true;
        }

        if (event instanceof MouseEvent) {
            if (event.button !== 0) {
                return true;
            }

            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return true;
            }
        }

        const target = event.target;
        if (!(target instanceof Element)) {
            return false;
        }

        return null !== target.closest('a,button,input,form,select,textarea,label,[data-row-link-ignore]');
    }
}
