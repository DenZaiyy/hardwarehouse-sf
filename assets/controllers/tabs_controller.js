import {Controller} from "@hotwired/stimulus"

// Onglets accessibles (motif Tabs des WAI-ARIA Authoring Practices) : un seul onglet atteignable par Tab, les flèches,
// Début et Fin déplacent la sélection. Le gabarit rend déjà l'onglet choisi, ce contrôleur ne fait que le changer.
export default class extends Controller {
    static targets = ['tab', 'panel']
    static values = { index: { type: Number, default: 0 } }

    connect() {
        this.showTab(this.indexValue)
    }

    select(event) {
        this.showTab(this.tabTargets.indexOf(event.currentTarget))
    }

    navigate(event) {
        const count = this.tabTargets.length
        const moves = {ArrowRight: this.indexValue + 1, ArrowLeft: this.indexValue - 1, Home: 0, End: count - 1}
        if (!(event.key in moves)) {
            return
        }

        event.preventDefault()
        const index = (moves[event.key] + count) % count
        this.showTab(index)
        this.tabTargets[index].focus()
    }

    showTab(index) {
        this.tabTargets.forEach((tab, i) => {
            const isActive = i === index
            tab.setAttribute('aria-selected', String(isActive))
            tab.tabIndex = isActive ? 0 : -1
            tab.classList.toggle('border-primary', isActive)
            tab.classList.toggle('text-primary', isActive)
            tab.classList.toggle('border-transparent', !isActive)
            tab.classList.toggle('text-muted', !isActive)
        })

        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index
        })

        this.indexValue = index
    }
}
