L.Control.Sidebar = L.Control.extend({
    options: {
        position: 'left',
        closeButton: true,
        autoPan: true,
    },

    initialize(placeholder, options = {}) {
        L.setOptions(this, options);

        this._contentContainer = L.DomUtil.get(placeholder);
        this._contentContainer.parentNode.removeChild(this._contentContainer);

        this._container = L.DomUtil.create(
            'div',
            `leaflet-sidebar ${this.options.position}`
        );

        L.DomUtil.addClass(this._contentContainer, 'leaflet-control');
        this._container.appendChild(this._contentContainer);

        if (this.options.closeButton) {
            this._closeButton = L.DomUtil.create('a', 'close', this._container);
            this._closeButton.innerHTML = '&times;';
        }
    },

    addTo(map) {
        this._map = map;

        const stop = L.DomEvent.stopPropagation;

        if (this._closeButton) {
            L.DomEvent.on(this._closeButton, 'click', this.hide, this);
        }

        L.DomEvent
            .on(this._container, 'transitionend', this._onTransition, this)
            .on(this._container, 'webkitTransitionEnd', this._onTransition, this);

        ['contextmenu', 'click', 'mousedown', 'touchstart', 'dblclick', 'wheel']
            .forEach(event =>
                L.DomEvent.on(this._contentContainer, event, stop)
            );

        map._controlContainer.insertBefore(
            this._container,
            map._controlContainer.firstChild
        );

        return this;
    },

    removeFrom(map) {
        this.hide();

        if (this._closeButton) {
            L.DomEvent.off(this._closeButton, 'click', this.hide, this);
        }

        ['contextmenu', 'click', 'mousedown', 'touchstart', 'dblclick', 'wheel']
            .forEach(event =>
                L.DomEvent.off(this._contentContainer, event, L.DomEvent.stopPropagation)
            );

        L.DomEvent
            .off(this._container, 'transitionend', this._onTransition, this)
            .off(this._container, 'webkitTransitionEnd', this._onTransition, this);

        map._controlContainer.removeChild(this._container);
        this._map = null;

        return this;
    },

    isVisible() {
        return this._container.classList.contains('visible');
    },

    show() {
        if (!this.isVisible()) {
            this._container.classList.add('visible');
            if (this.options.autoPan) {
                this._map.panBy([-this._getOffset() / 2, 0], { duration: 0.5 });
            }
            this.fire('show');
        }
    },

    hide(e) {
        if (this.isVisible()) {
            this._container.classList.remove('visible');
            if (this.options.autoPan) {
                this._map.panBy([this._getOffset() / 2, 0], { duration: 0.5 });
            }
            this.fire('hide');
        }
        if (e) L.DomEvent.stopPropagation(e);
    },

    toggle() {
        this.isVisible() ? this.hide() : this.show();
    },

    setContent(html) {
        this._contentContainer.innerHTML = html;
        return this;
    },

    getContainer() {
        return this._contentContainer;
    },

    getCloseButton() {
        return this._closeButton;
    },

    _getOffset() {
        return this.options.position === 'right'
            ? -this._container.offsetWidth
            : this._container.offsetWidth;
    },

    _onTransition(e) {
        if (e.propertyName === 'left' || e.propertyName === 'right') {
            this.fire(this.isVisible() ? 'shown' : 'hidden');
        }
    },
});

L.control.sidebar = function (placeholder, options) {
    return new L.Control.Sidebar(placeholder, options);
};
