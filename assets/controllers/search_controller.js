import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "results"]
    static values = {
        url: String,
        debounceDelay: { type: Number, default: 300 }
    }

    connect() {
        this.timeout = null
        // Fermer les résultats si on clique ailleurs
        document.addEventListener('click', this.handleOutsideClick.bind(this))
    }

    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout)
        }
        document.removeEventListener('click', this.handleOutsideClick.bind(this))
    }

    query() {
        const query = this.inputTarget.value.trim()

        // Clear previous timeout
        if (this.timeout) {
            clearTimeout(this.timeout)
        }

        // Si la recherche est vide, cacher les résultats
        if (query.length < 2) {
            this.hideResults()
            return
        }

        // Debounce la recherche
        this.timeout = setTimeout(() => {
            this.search(query)
        }, this.debounceDelayValue)
    }

    async search(query) {
        try {
            const url = this.urlValue || '/search'
            const response = await fetch(`${url}?search=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                this.displayResults(data.products || [])
            }
        } catch (error) {
            console.error('Search error:', error)
            this.hideResults()
        }
    }

    displayResults(products) {
        if (products.length === 0) {
            this.resultsTarget.innerHTML = '<div class="p-4 text-gray-500 text-sm">Aucun produit trouvé</div>'
        } else {
            this.resultsTarget.innerHTML = products.map(product => `
                <div class="p-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0 text-primary" onclick="window.location.href='${product.url || '#'}'">
                    <div class="flex items-center gap-3">
                        ${product.thumbnail ? `<img src="${product.thumbnail}" class="w-10 h-10 object-cover rounded" alt="${product.name}">` : ''}
                        <div class="flex-1">
                            <div class="font-medium text-sm">${product.name}</div>
                            ${product.price ? `<div class="text-xs text-gray-600">${product.price}€</div>` : ''}
                        </div>
                    </div>
                </div>
            `).join('')
        }

        this.showResults()
    }

    showResults() {
        this.resultsTarget.classList.remove('hidden')
    }

    hideResults() {
        this.resultsTarget.classList.add('hidden')
    }

    handleOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.hideResults()
        }
    }
}
