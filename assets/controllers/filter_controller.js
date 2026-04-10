import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["form", "results", "loading", "count"]
    static values = {
        filterUrl: String,
        urlPath: { type: String, default: "" },
        debounce: { type: Number, default: 300 }
    }

    connect() {
        this.debounceTimer = null

        this.onFormInput = () => this.debouncedFilter()
        this.onFormChange = () => this.debouncedFilter()
        this.onPaginationClick = (event) => this.handlePaginationClick(event)

        this.formTarget.addEventListener("input", this.onFormInput)
        this.formTarget.addEventListener("change", this.onFormChange)
        this.resultsTarget.addEventListener("click", this.onPaginationClick)

        this.initFromUrl()
        this.updateActiveCount()
    }

    disconnect() {
        this.formTarget.removeEventListener("input", this.onFormInput)
        this.formTarget.removeEventListener("change", this.onFormChange)
        this.resultsTarget.removeEventListener("click", this.onPaginationClick)

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer)
        }
    }

    debouncedFilter() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer)
        }

        this.debounceTimer = setTimeout(() => {
            this.filter(1)
        }, this.debounceValue)
    }

    async filter(page = 1) {
        this.showLoading()

        try {
            const params = this.buildParams()
            params.set("page", String(page))

            const response = await fetch(`${this.filterUrlValue}?${params.toString()}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                }
            })

            if (!response.ok) {
                console.error(`[filter] HTTP ${response.status}`)
                return
            }

            const data = await response.json()

            if (!data.success) {
                console.error("[filter]", data.error ?? "Unknown error")
                return
            }

            this.updateResults(data.productsHtml ?? "", data.paginationHtml ?? "")
            this.updateUrl(params)
            this.updateActiveCount()
        } catch (error) {
            console.error("[filter] Request failed:", error)
        } finally {
            this.hideLoading()
        }
    }

    buildParams() {
        const params = new URLSearchParams()
        const multiValues = {}

        for (const element of this.formTarget.elements) {
            if (!element.name || element.disabled) {
                continue
            }

            const paramName = element.dataset.filterParam || element.name

            if (element.type === "checkbox") {
                if (!multiValues[paramName]) {
                    multiValues[paramName] = []
                }

                if (element.checked) {
                    multiValues[paramName].push(element.value)
                }

                continue
            }

            if (element.tagName === "SELECT") {
                if (element.value !== "") {
                    params.set(paramName, element.value)
                }
                continue
            }

            if (["number", "range"].includes(element.type)) {
                if (element.value !== "" && element.value !== "0") {
                    params.set(paramName, element.value)
                }
                continue
            }

            if (["text", "search"].includes(element.type)) {
                const value = element.value.trim()
                if (value !== "") {
                    params.set(paramName, value)
                }
            }
        }

        Object.entries(multiValues).forEach(([key, values]) => {
            if (values.length > 0) {
                params.set(key, values.join(","))
            }
        })

        return params
    }

    initFromUrl() {
        const params = new URLSearchParams(window.location.search)

        for (const element of this.formTarget.elements) {
            if (!element.name || element.disabled) {
                continue
            }

            const paramName = element.dataset.filterParam || element.name
            const paramValue = params.get(paramName)

            if (element.type === "checkbox") {
                if (!paramValue) {
                    element.checked = false
                    continue
                }

                const values = paramValue.split(",").map(v => v.trim())
                element.checked = values.includes(element.value)
                continue
            }

            if (element.tagName === "SELECT") {
                if (paramValue !== null) {
                    element.value = paramValue
                }
                continue
            }

            if (["number", "range", "text", "search"].includes(element.type)) {
                if (paramValue !== null) {
                    element.value = paramValue
                }
            }
        }
    }

    handlePaginationClick(event) {
        const pageBtn = event.target.closest("[data-page]")
        if (!pageBtn) {
            return
        }

        event.preventDefault()

        const page = parseInt(pageBtn.dataset.page, 10)
        if (isNaN(page) || page < 1) {
            return
        }

        this.filter(page)
        this.resultsTarget.scrollIntoView({ behavior: "smooth", block: "start" })
    }

    updateResults(productsHtml, paginationHtml) {
        const grid = this.resultsTarget.querySelector("[data-filter-grid]")
        if (grid) {
            grid.outerHTML = productsHtml
        }

        const pagination = this.resultsTarget.querySelector("[data-filter-pagination]")
        if (pagination) {
            pagination.innerHTML = paginationHtml
        }
    }

    updateUrl(params) {
        const basePath = this.urlPathValue || window.location.pathname
        const query = params.toString()
        const url = query ? `${basePath}?${query}` : basePath

        window.history.pushState({}, "", url)
    }

    updateActiveCount() {
        if (!this.hasCountTarget) {
            return
        }

        let count = 0
        const multiCounted = new Set()

        for (const element of this.formTarget.elements) {
            if (!element.name || element.disabled) {
                continue
            }

            const paramName = element.dataset.filterParam || element.name

            if (element.type === "checkbox") {
                if (multiCounted.has(paramName)) {
                    continue
                }

                const checkboxes = Array.from(this.formTarget.querySelectorAll(`input[type="checkbox"]`))
                    .filter(input => (input.dataset.filterParam || input.name) === paramName)

                const checked = checkboxes.some(input => input.checked)
                if (checked) {
                    count++
                }

                multiCounted.add(paramName)
                continue
            }

            if (element.tagName === "SELECT") {
                if (element.value !== "") {
                    count++
                }
                continue
            }

            if (["number", "range"].includes(element.type)) {
                if (element.value !== "" && element.value !== "0") {
                    count++
                }
                continue
            }

            if (["text", "search"].includes(element.type)) {
                if (element.value.trim() !== "") {
                    count++
                }
            }
        }

        this.countTarget.textContent = count > 0 ? `(${count})` : ""
        this.countTarget.classList.toggle("hidden", count === 0)
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove("hidden")
        }

        this.resultsTarget.style.opacity = "0.5"
        this.resultsTarget.style.pointerEvents = "none"
    }

    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add("hidden")
        }

        this.resultsTarget.style.opacity = "1"
        this.resultsTarget.style.pointerEvents = "auto"
    }

    clearAll() {
        for (const element of this.formTarget.elements) {
            if (!element.name || element.disabled) {
                continue
            }

            if (element.type === "checkbox") {
                element.checked = false
                continue
            }

            if (element.tagName === "SELECT") {
                element.selectedIndex = 0
                continue
            }

            if (["number", "range", "text", "search"].includes(element.type)) {
                element.value = ""
            }
        }

        this.filter(1)
    }
}
