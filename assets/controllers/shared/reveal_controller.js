import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default class extends Controller {
    static targets = ['item'];

    connect() {
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.isMobile = window.matchMedia('(max-width: 767px)').matches;

        if (this.reduceMotion) {
            gsap.set(this.itemsOrSelf(), { clearProps: 'all', opacity: 1, y: 0 });
            return;
        }

        this.animate();
    }

    disconnect() {
        ScrollTrigger.getAll().forEach((trigger) => {
            if (trigger.trigger === this.element || this.itemTargets.includes(trigger.trigger)) {
                trigger.kill();
            }
        });
    }

    animate() {
        const targets = this.itemsOrSelf();

        gsap.fromTo(
            targets,
            {
                y: this.isMobile ? 18 : 28,
                opacity: 0,
            },
            {
                y: 0,
                opacity: 1,
                duration: this.isMobile ? 0.5 : 0.7,
                stagger: this.hasItemTarget ? (this.isMobile ? 0.06 : 0.1) : 0,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: this.element,
                    start: this.isMobile ? 'top 96%' : 'top 90%',
                    once: true,
                },
                clearProps: 'transform,opacity',
            }
        );
    }

    itemsOrSelf() {
        return this.hasItemTarget ? this.itemTargets : this.element;
    }
}
