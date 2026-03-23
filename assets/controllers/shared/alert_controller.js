import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    connect() {
        this.setupAutoRemove();
    }

    setupAutoRemove() {
        setTimeout(() => {
            this.element.style.opacity = '0';
            this.element.addEventListener('transitionend', () => {
                this.element.remove();
            });
        }, 3000);
    }
}
