import {Controller} from "@hotwired/stimulus"

// Boutons − et + d'un champ de quantité : la valeur reste entre le min et le max du champ (le stock disponible).
export default class extends Controller {
    static targets = ['input']

    decrement() {
        this.step(-1)
    }

    increment() {
        this.step(1)
    }

    step(delta) {
        const input = this.inputTarget
        const min = Number(input.min || 1)
        const max = input.max === '' ? Infinity : Number(input.max)
        const current = Number.parseInt(input.value, 10) || min

        input.value = String(Math.min(max, Math.max(min, current + delta)))
        input.dispatchEvent(new Event('change', {bubbles: true}))
    }
}
