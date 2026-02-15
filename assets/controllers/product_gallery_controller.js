import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['mainImage', 'images', 'counter', 'lightbox', 'lightboxImage']
    static values = {
        index: { type: Number, default: 0 },
        autoplay: { type: Boolean, default: true },
        interval: { type: Number, default: 3000 }
    }

    connect() {
        this.totalImages = this.imagesTargets.length
        this.isHovered = false
        this.autoplayTimer = null
        
        this.updateActiveState()
        this.setupKeyboardNavigation()
        this.setupSwipeGestures()
        this.setupAutoplay()
    }


    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown)
        this.stopAutoplay()
    }

    setupKeyboardNavigation() {
        this.handleKeydown = (e) => {
            if (this.isLightboxOpen()) {
                if (e.key === 'Escape') this.closeLightbox()
                if (e.key === 'ArrowLeft') this.previous()
                if (e.key === 'ArrowRight') this.next()
            }
        }
        document.addEventListener('keydown', this.handleKeydown)
    }

    setupSwipeGestures() {
        let touchStartX = 0
        let touchEndX = 0

        this.element.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX
        }, { passive: true })

        this.element.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX
            this.handleSwipe(touchStartX, touchEndX)
        }, { passive: true })
    }

    handleSwipe(startX, endX) {
        const threshold = 50
        const diff = startX - endX

        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                this.next()
            } else {
                this.previous()
            }
        }
    }


    updateMainImage() {
        const activeImages = this.imagesTargets[this.indexValue]
        if (!activeImages) return

        const newSrc = activeImages.dataset.fullSrc
        const newSrcset = activeImages.dataset.srcset || ''
        const newAlt = activeImages.alt

        this.mainImageTarget.style.opacity = '0'

        setTimeout(() => {
            this.mainImageTarget.src = newSrc
            if (newSrcset) {
                this.mainImageTarget.srcset = newSrcset
            }
            this.mainImageTarget.alt = newAlt
            this.mainImageTarget.style.opacity = '1'
        }, 150)

        this.counterTargets.forEach(counter => {
            counter.textContent = `${this.indexValue + 1} / ${this.totalImages}`
        })

        if (this.hasLightboxImageTarget && this.isLightboxOpen()) {
            this.lightboxImageTarget.src = newSrc
            this.lightboxImageTarget.alt = newAlt
        }
    }

    updateActiveState() {
        this.imagesTargets.forEach((thumb, index) => {
            const isActive = index === this.indexValue
            thumb.classList.toggle('border-blue-500', isActive)
            thumb.classList.toggle('border-transparent', !isActive)
            thumb.classList.toggle('opacity-100', isActive)
            thumb.classList.toggle('opacity-60', !isActive)
            thumb.setAttribute('aria-selected', isActive)
        })

        // Scroll la miniature active dans la vue (desktop)
        const activeThumb = this.imagesTargets[this.indexValue]
        if (activeThumb) {
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' })
        }
    }


    isLightboxOpen() {
        return this.hasLightboxTarget && !this.lightboxTarget.classList.contains('hidden')
    }

    // Zoom sur l'image principale (hover desktop)
    handleZoom(event) {
        if (window.innerWidth < 1024) return // Désactivé sur mobile/tablette

        const container = event.currentTarget
        const img = this.mainImageTarget
        const rect = container.getBoundingClientRect()

        const x = ((event.clientX - rect.left) / rect.width) * 100
        const y = ((event.clientY - rect.top) / rect.height) * 100

        img.style.transformOrigin = `${x}% ${y}%`
        img.style.transform = 'scale(1.5)'
    }

    resetZoom() {
        this.mainImageTarget.style.transform = 'scale(1)'
    }

    setupAutoplay() {
        if (!this.autoplayValue || this.totalImages <= 1) return

        this.element.addEventListener('mouseenter', () => {
            this.isHovered = true
            this.stopAutoplay()
        })

        this.element.addEventListener('mouseleave', () => {
            this.isHovered = false
            this.startAutoplay()
        })

        this.startAutoplay()
    }

    startAutoplay() {
        if (!this.autoplayValue || this.totalImages <= 1 || this.isHovered || this.isLightboxOpen()) return
        
        this.stopAutoplay()
        this.autoplayTimer = setInterval(() => {
            if (!this.isHovered && !this.isLightboxOpen()) {
                this.next()
            }
        }, this.intervalValue)
    }

    stopAutoplay() {
        if (this.autoplayTimer) {
            clearInterval(this.autoplayTimer)
            this.autoplayTimer = null
        }
    }

    select(event) {
        this.indexValue = parseInt(event.currentTarget.dataset.index, 10)
        this.updateMainImage()
        this.updateActiveState()
        this.stopAutoplay()
        setTimeout(() => this.startAutoplay(), 1000)
    }

    previous() {
        this.indexValue = this.indexValue > 0 ? this.indexValue - 1 : this.totalImages - 1
        this.updateMainImage()
        this.updateActiveState()
        this.stopAutoplay()
        setTimeout(() => this.startAutoplay(), 1000)
    }

    next() {
        this.indexValue = this.indexValue < this.totalImages - 1 ? this.indexValue + 1 : 0
        this.updateMainImage()
        this.updateActiveState()
    }

    openLightbox() {
        if (!this.hasLightboxTarget) return

        this.stopAutoplay()

        const activeImages = this.imagesTargets[this.indexValue]
        if (activeImages) {
            this.lightboxImageTarget.src = activeImages.dataset.fullSrc
            this.lightboxImageTarget.alt = activeImages.alt
        }

        this.lightboxTarget.classList.remove('hidden')
        this.lightboxTarget.classList.add('flex')
        document.body.style.overflow = 'hidden'

        this.lightboxTarget.focus()
    }

    closeLightbox() {
        if (!this.hasLightboxTarget) return

        this.lightboxTarget.classList.add('hidden')
        this.lightboxTarget.classList.remove('flex')
        document.body.style.overflow = ''
        
        this.startAutoplay()
    }

}
