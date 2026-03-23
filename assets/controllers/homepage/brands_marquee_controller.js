import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';

export default class extends Controller {
    static targets = ['track'];

    connect() {
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.isMobile = window.matchMedia('(max-width: 767px)').matches;

        if (this.reduceMotion || this.isMobile || !this.hasTrackTarget) {
            return;
        }

        const totalWidth = this.trackTarget.scrollWidth / 2;

        if (!totalWidth || totalWidth < 300) {
            return;
        }

        this.tween = gsap.fromTo(
            this.trackTarget,
            { x: 0 },
            {
                x: -totalWidth,
                duration: 28,
                ease: 'none',
                repeat: -1,
            }
        );
    }

    disconnect() {
        if (this.tween) {
            this.tween.kill();
        }
    }
}
