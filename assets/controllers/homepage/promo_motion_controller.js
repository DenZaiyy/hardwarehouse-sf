import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';

export default class extends Controller {
    static targets = ['orbOne', 'orbTwo'];

    connect() {
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (this.reduceMotion) {
            return;
        }

        if (this.hasOrbOneTarget) {
            this.orbOneTween = gsap.to(this.orbOneTarget, {
                x: -18,
                y: 12,
                duration: 6.5,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            });
        }

        if (this.hasOrbTwoTarget) {
            this.orbTwoTween = gsap.to(this.orbTwoTarget, {
                x: 14,
                y: -10,
                duration: 7.5,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            });
        }
    }

    disconnect() {
        if (this.orbOneTween) this.orbOneTween.kill();
        if (this.orbTwoTween) this.orbTwoTween.kill();
    }
}
