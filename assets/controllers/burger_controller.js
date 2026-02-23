import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static values = { target: String }

    toggle() {
        const targetElement = document.querySelector(this.targetValue)
        if (targetElement) {
            const isHidden = targetElement.classList.contains('hidden')

            if (isHidden) {
                // Opening
                targetElement.classList.remove('hidden')
                targetElement.classList.add('opacity-0')
                document.body.classList.add('overflow-hidden')

                // Trigger animation
                requestAnimationFrame(() => {
                    targetElement.classList.remove('opacity-0')
                    targetElement.classList.add('opacity-100')
                })
            } else {
                // Closing
                targetElement.classList.remove('opacity-100')
                targetElement.classList.add('opacity-0')

                // Hide after animation
                setTimeout(() => {
                    targetElement.classList.add('hidden')
                    document.body.classList.remove('overflow-hidden')
                }, 200) // Match transition duration
            }
        }
    }
}
