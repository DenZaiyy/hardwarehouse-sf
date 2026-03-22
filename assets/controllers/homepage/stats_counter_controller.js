import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default class extends Controller {
    static targets = ['number'];

    connect() {
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.isMobile = window.matchMedia('(max-width: 767px)').matches;

        if (this.reduceMotion) {
            this.renderFinalValues();
            return;
        }

        this.animate();
    }

    disconnect() {
        ScrollTrigger.getAll().forEach((trigger) => {
            if (trigger.trigger === this.element) {
                trigger.kill();
            }
        });
    }

    animate() {
        this.numberTargets.forEach((element) => {
            const endValue = Number(element.dataset.value || 0);
            const decimals = Number(element.dataset.decimals || 0);
            const suffix = element.dataset.suffix || '';
            const counter = { value: 0 };

            gsap.to(counter, {
                value: endValue,
                duration: 1.4,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: this.element,
                    start: this.isMobile ? 'top 95%' : 'top 88%',
                    once: true,
                },
                onUpdate: () => {
                    element.textContent = `${this.format(counter.value, decimals)}${suffix}`;
                },
            });
        });
    }

    renderFinalValues() {
        this.numberTargets.forEach((element) => {
            const endValue = Number(element.dataset.value || 0);
            const decimals = Number(element.dataset.decimals || 0);
            const suffix = element.dataset.suffix || '';

            element.textContent = `${this.format(endValue, decimals)}${suffix}`;
        });
    }

    format(value, decimals) {
        if (decimals > 0) {
            return value.toFixed(decimals);
        }

        return Math.round(value).toLocaleString('fr-FR');
    }
}
