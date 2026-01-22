import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['tab', 'panel']
    static values = { index: { type: Number, default: 0 } }

    connect() {
        this.showTab(this.indexValue)
    }

    select(event) {
        const index = this.tabTargets.indexOf(event.currentTarget)
        this.showTab(index)
    }

    showTab(index) {
        this.tabTargets.forEach((tab, i) => {
            const isActive = i === index
            tab.setAttribute('aria-selected', isActive)
            tab.classList.toggle('border-blue-500', isActive)
            tab.classList.toggle('text-blue-600', isActive)
            tab.classList.toggle('border-transparent', !isActive)
            tab.classList.toggle('text-gray-500', !isActive)
        })

        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index
        })

        this.indexValue = index
    }
}
