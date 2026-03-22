import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["container", "content"]

    connect() {
        this.closeModalHandler = () => this.close()
        this.frameLoadHandler = () => {
            if (this.contentTarget.innerHTML.trim()) {
                this.open()
            }
        }

        window.addEventListener('modal:close', this.closeModalHandler)
        this.contentTarget.addEventListener('turbo:frame-load', this.frameLoadHandler)
    }

    disconnect() {
        window.removeEventListener('modal:close', this.closeModalHandler)
        this.contentTarget.removeEventListener('turbo:frame-load', this.frameLoadHandler)
    }

    open() {
        this.containerTarget.classList.remove("hidden");
        this.containerTarget.classList.add("flex");
    }

    close() {
        this.containerTarget.classList.add("hidden")
        this.containerTarget.classList.remove("flex")
    }
}
