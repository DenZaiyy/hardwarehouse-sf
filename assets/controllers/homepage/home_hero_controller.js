import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';

export default class extends Controller {
    static targets = ['eyebrow', 'title', 'text', 'actions', 'visual', 'image', 'stats'];

    connect() {
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (this.reduceMotion) {
            this.targetsForReset().forEach((element) => {
                gsap.set(element, { clearProps: 'all', opacity: 1, y: 0, x: 0, scale: 1 });
            });
            return;
        }

        this.timeline = gsap.timeline({ defaults: { ease: 'power3.out' } });

        this.timeline
            .from(this.eyebrowTarget, { y: 18, opacity: 0, duration: 0.55 })
            .from(this.titleTarget, { y: 30, opacity: 0, duration: 0.75 }, '-=0.25')
            .from(this.textTarget, { y: 20, opacity: 0, duration: 0.6 }, '-=0.35')
            .from(this.actionsTarget.children, {
                y: 18,
                opacity: 0,
                duration: 0.5,
                stagger: 0.12,
            }, '-=0.25')
            .from(this.visualTarget, { x: 28, opacity: 0, duration: 0.9 }, '-=0.7')
            .from(this.imageTarget, { scale: 1.08, duration: 1.4, ease: 'power2.out' }, '-=0.9')
            .from(this.statsTarget.children, {
                y: 18,
                opacity: 0,
                duration: 0.5,
                stagger: 0.1,
            }, '-=0.65');
    }

    disconnect() {
        if (this.timeline) {
            this.timeline.kill();
        }
    }

    targetsForReset() {
        return [
            this.eyebrowTarget,
            this.titleTarget,
            this.textTarget,
            this.actionsTarget,
            this.visualTarget,
            this.imageTarget,
            this.statsTarget,
        ].filter(Boolean);
    }
}
