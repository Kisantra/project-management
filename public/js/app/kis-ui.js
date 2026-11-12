/*
 * Kisantra UI kit: Alpine data for <x-kis.filter-rail> and <x-kis.month-picker>.
 * Visual rules live in DESIGN.md; markup lives in resources/views/components/kis.
 *
 * Both components are rendered fully on the server (no x-for), so Livewire's
 * morph never has to reconcile Alpine-generated nodes. Each exposes `value`
 * through x-modelable, which makes `wire:model.live="..."` work on the tag.
 */
(() => {
    const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    const QUARTERS = { 1: [1, 2, 3], 2: [4, 5, 6], 3: [7, 8, 9], 4: [10, 11, 12], all: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] }

    const keyOf = (value) => (value === null || value === undefined || value === '' ? '' : String(value))

    /**
     * One-row filter: as many chips as fit, the rest behind "+N lainnya".
     * Selected options always stay visible (pinned at the end of the row).
     * With `multiple`, `value` is a list and every chip toggles; "Semua"
     * clears the list.
     */
    function filterRail({ options = [], allLabel = 'Semua', multiple = false } = {}) {
        return {
            value: multiple ? [] : null,
            multiple,
            options: options.map((option) => ({ ...option, key: keyOf(option.value) })),
            allLabel,
            visibleKeys: null,
            pinnedKeys: [],
            hiddenCount: 0,
            layoutToken: 0,
            lastWidth: null,
            open: false,
            query: '',
            highlighted: 0,

            init() {
                // Only a change in width can change what fits; hiding chips
                // also nudges the observer, which must not loop back here.
                this.observer = new ResizeObserver(([entry]) => {
                    const width = Math.round(entry.contentRect.width)
                    if (width === this.lastWidth) return
                    this.lastWidth = width
                    this.layout()
                })
                this.observer.observe(this.$refs.wrap)
                this.$watch('value', () => this.layout())
                document.fonts?.ready.then(() => this.layout())
                this.layout()
            },

            destroy() {
                this.observer?.disconnect()
            },

            // Selected keys as strings; "" stands for "Semua".
            selectedKeys() {
                if (!this.multiple) return [keyOf(this.value)]
                const keys = (Array.isArray(this.value) ? this.value : [this.value]).map(keyOf).filter((key) => key !== '')
                return keys.length ? keys : ['']
            },

            isSelected(value) {
                return this.selectedKeys().includes(keyOf(value))
            },

            isPinned(key) {
                return this.pinnedKeys.includes(key)
            },

            select(value) {
                const key = keyOf(value)
                if (!this.multiple) {
                    this.value = key === '' ? null : value
                    return
                }
                if (key === '') {
                    this.value = []
                    return
                }
                const current = this.selectedKeys().filter((selected) => selected !== '')
                this.value = current.includes(key)
                    ? current.filter((selected) => selected !== key)
                    : [...current, key]
            },

            isShown(key) {
                return this.visibleKeys === null || this.visibleKeys.includes(key)
            },

            // Hidden chips stay in the DOM with visibility: hidden and absolute
            // position, so every chip can be measured at any time without
            // revealing it first. Calls can arrive back to back (init, value,
            // fonts, resize); only the latest one measures. A rail with no width
            // yet (hidden tab, collapsed section) is measured again when the
            // observer sees a size.
            layout() {
                const token = ++this.layoutToken
                requestAnimationFrame(() => this.measure(token))
            },

            measure(token) {
                {
                    const wrap = this.$refs.wrap
                    if (token !== this.layoutToken || !wrap || wrap.clientWidth === 0) return
                    const chips = [...this.$refs.row.querySelectorAll('[data-key]')]
                    if (chips.length === 0) return

                    const styles = getComputedStyle(wrap)
                    const inner = wrap.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight)
                    const gap = 2
                    const widths = chips.map((chip) => chip.offsetWidth + gap)
                    const fit = (available) => {
                        const keys = []
                        let used = 0
                        for (const [index, chip] of chips.entries()) {
                            if (used + widths[index] > available) break
                            keys.push(chip.dataset.key)
                            used += widths[index]
                        }
                        return { keys, used }
                    }

                    let { keys, used } = fit(inner)
                    if (keys.length < chips.length) {
                        ;({ keys, used } = fit(inner - (this.$refs.more?.offsetWidth || 120) - 8))
                    }

                    // Selected chips that did not fit replace unselected ones at the end of the row.
                    const selected = this.selectedKeys()
                    const missing = selected
                        .map((key) => chips.findIndex((chip) => chip.dataset.key === key))
                        .filter((index) => index !== -1 && !keys.includes(chips[index].dataset.key))
                    const pinned = []
                    if (missing.length) {
                        const available = inner - (this.$refs.more?.offsetWidth || 120) - 8
                        const extra = missing.reduce((sum, index) => sum + widths[index] + 12, 0)
                        for (let i = keys.length - 1; i > 0 && used + extra > available; i--) {
                            if (selected.includes(keys[i])) continue
                            used -= widths[chips.findIndex((chip) => chip.dataset.key === keys[i])]
                            keys.splice(i, 1)
                        }
                        for (const index of missing) {
                            keys.push(chips[index].dataset.key)
                            pinned.push(chips[index].dataset.key)
                        }
                    }
                    this.pinnedKeys = pinned

                    this.visibleKeys = keys
                    this.hiddenCount = chips.length - keys.length
                }
            },

            toggle() {
                this.open ? this.close() : this.show()
            },

            show() {
                this.open = true
                this.query = ''
                this.highlighted = 0
                this.$nextTick(() => this.$refs.search?.focus({ preventScroll: true }))
            },

            close(returnFocus = false) {
                if (!this.open) return
                this.open = false
                if (returnFocus) this.$refs.more?.focus({ preventScroll: true })
            },

            matches(option) {
                return option.label.toLowerCase().includes(this.query.trim().toLowerCase())
            },

            // Display order in the overflow list: options hidden from the row first.
            listKeys() {
                const visible = this.options.filter((option) => this.matches(option))
                if (this.query.trim()) return visible.map((option) => option.key)
                const notInRow = visible.filter((option) => !this.isShown(option.key))
                const inRow = visible.filter((option) => this.isShown(option.key))
                return [...notInRow, ...inRow].map((option) => option.key)
            },

            listOrder(key) {
                const index = this.listKeys().indexOf(key)
                if (index === -1) return 0
                if (this.query.trim()) return index + 1
                return this.isShown(key) ? 1000 + index : 1 + index
            },

            hasSection(inRow) {
                if (this.query.trim()) return false
                return this.options.some((option) => this.isShown(option.key) === inRow)
            },

            isHighlighted(key) {
                return this.listKeys()[this.highlighted] === key
            },

            parts(label) {
                const query = this.query.trim()
                const index = query ? label.toLowerCase().indexOf(query.toLowerCase()) : -1
                if (index === -1) return [label, '', '']
                return [label.slice(0, index), label.slice(index, index + query.length), label.slice(index + query.length)]
            },

            onSearchKeydown(event) {
                const keys = this.listKeys()
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault()
                    if (keys.length === 0) return
                    this.highlighted = (this.highlighted + (event.key === 'ArrowDown' ? 1 : -1) + keys.length) % keys.length
                    this.$nextTick(() => this.$refs.list?.querySelector('[data-highlighted="true"]')?.scrollIntoView({ block: 'nearest' }))
                }
                if (event.key === 'Enter' && keys[this.highlighted] !== undefined) {
                    event.preventDefault()
                    this.pick(this.options.find((option) => option.key === keys[this.highlighted]).value)
                }
            },

            pick(value) {
                this.select(value)
                // Several options can be ticked in one visit.
                if (!this.multiple) this.close(true)
            },
        }
    }

    /**
     * Multi-select month filter. `value` is `{ year, months: [] }`; an empty
     * month list means "semua bulan". Changes apply immediately.
     */
    function monthPicker({ counts = {}, nowYear, nowMonth } = {}) {
        return {
            value: { year: nowYear, months: [] },
            counts,
            nowYear,
            nowMonth,
            anchor: null,
            open: false,

            get year() {
                return Number(this.value?.year ?? this.nowYear)
            },

            get months() {
                return (this.value?.months ?? []).map(Number)
            },

            yearData(year) {
                return this.counts[year] ?? this.counts[String(year)] ?? null
            },

            hasYear(year) {
                return this.yearData(year) !== null
            },

            count(month, year = this.year) {
                const data = this.yearData(year)
                if (data === null) return 0
                return Number((Array.isArray(data) ? data[month - 1] : data[month] ?? data[String(month)]) ?? 0)
            },

            total(year = this.year) {
                return QUARTERS.all.reduce((sum, month) => sum + this.count(month, year), 0)
            },

            isSelected(month) {
                return this.months.includes(month)
            },

            isNow(month) {
                return this.year === this.nowYear && month === this.nowMonth
            },

            set(months, year = this.year) {
                this.value = { year, months: [...new Set(months)].sort((a, b) => a - b) }
            },

            toggleMonth(month, event) {
                if (this.count(month) === 0) return
                if (event?.shiftKey && this.anchor !== null) {
                    const [from, to] = [Math.min(this.anchor, month), Math.max(this.anchor, month)]
                    const range = QUARTERS.all.filter((m) => m >= from && m <= to && this.count(m) > 0)
                    this.set([...this.months, ...range])
                } else {
                    this.set(this.isSelected(month) ? this.months.filter((m) => m !== month) : [...this.months, month])
                }
                this.anchor = month
            },

            presetMonths(preset) {
                return QUARTERS[preset].filter((month) => this.count(month) > 0)
            },

            isPresetActive(preset) {
                const wanted = this.presetMonths(preset)
                if (wanted.length === 0 || !wanted.every((month) => this.isSelected(month))) return false
                return preset !== 'all' || this.months.length === wanted.length
            },

            applyPreset(preset) {
                const wanted = this.presetMonths(preset)
                const active = this.isPresetActive(preset)
                if (preset === 'all') this.set(active ? [] : wanted)
                else this.set(active ? this.months.filter((m) => !wanted.includes(m)) : [...this.months, ...wanted])
            },

            stepYear(direction) {
                const year = this.year + direction
                if (!this.hasYear(year)) return
                this.anchor = null
                this.set([], year)
            },

            reset() {
                this.anchor = null
                this.set([])
            },

            summary() {
                const months = this.months
                const year = this.year
                if (months.length === 0) return 'Semua bulan'
                if (months.length === 12) return `Setahun ${year}`
                if (months.length === 1) return `${MONTHS[months[0] - 1]} ${year}`
                const contiguous = months.every((month, index) => index === 0 || month === months[index - 1] + 1)
                if (contiguous) return `${MONTHS_SHORT[months[0] - 1]} – ${MONTHS_SHORT[months.at(-1) - 1]} ${year}`
                if (months.length <= 3) return `${months.map((month) => MONTHS_SHORT[month - 1]).join(', ')} ${year}`
                return `${months.length} bulan · ${year}`
            },

            monthTitle(month) {
                return `${MONTHS[month - 1]} ${this.year}${this.isNow(month) ? ' · bulan ini' : ''}`
            },

            toggle() {
                this.open ? this.close() : this.show()
            },

            show() {
                this.open = true
                this.$nextTick(() => {
                    const target = this.$refs.grid?.querySelector('[aria-pressed="true"]:not(:disabled)') ?? this.$refs.grid?.querySelector('button:not(:disabled)')
                    target?.focus({ preventScroll: true })
                })
            },

            close(returnFocus = false) {
                if (!this.open) return
                this.open = false
                if (returnFocus) this.$refs.trigger?.focus({ preventScroll: true })
            },

            // Arrow keys move through the 4x3 grid, skipping empty months.
            onGridKeydown(event) {
                const steps = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: 4, ArrowUp: -4 }
                if (!(event.key in steps)) return
                event.preventDefault()
                const buttons = [...this.$refs.grid.querySelectorAll('button')]
                let index = buttons.indexOf(document.activeElement)
                for (let i = 0; i < 12; i++) {
                    index = (index + steps[event.key] + 12) % 12
                    if (!buttons[index].disabled) return buttons[index].focus()
                }
            },
        }
    }

    /*
     * Tabs whose choice lives in one query parameter (?jenis=, ?tab=), so a
     * reload or a month switch keeps the place. `value` is what the server
     * rendered; replaceState keeps Back for real navigation. `resets` lists
     * parameters that belong to the previous choice (a sub-tab of another type).
     */
    function urlTab({ param, value = null, fallback, allowed = [], resets = [] } = {}) {
        return {
            tab: allowed.includes(value) ? value : fallback,
            show(next) {
                if (!allowed.includes(next) || next === this.tab) return
                this.tab = next
                const url = new URL(window.location.href)
                next === fallback ? url.searchParams.delete(param) : url.searchParams.set(param, next)
                resets.forEach((key) => url.searchParams.delete(key))
                window.history.replaceState(window.history.state, '', url)
            },
        }
    }

    const register = () => {
        window.Alpine.data('kisFilterRail', filterRail)
        window.Alpine.data('kisMonthPicker', monthPicker)
        window.Alpine.data('kisUrlTab', urlTab)
    }

    /*
     * Follow a link but keep the current query string (the open tab), for
     * links between periods or clients of the same page. Modified clicks
     * (new tab, new window) behave as usual.
     */
    window.kisFollow = (event, el) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button > 0) return
        event.preventDefault()
        const url = new URL(el.href, window.location.href)
        url.search = window.location.search
        window.Livewire?.navigate ? window.Livewire.navigate(url.href) : window.location.assign(url.href)
    }

    if (window.Alpine) register()
    document.addEventListener('alpine:init', register)

    /*
     * Copy text to the clipboard. navigator.clipboard only exists on https and
     * localhost, so plain-http servers fall back to a hidden textarea.
     */
    window.kisCopy = (text) => {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(String(text))

        return new Promise((resolve, reject) => {
            const area = document.createElement('textarea')
            area.value = String(text)
            area.setAttribute('readonly', '')
            area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none'
            document.body.appendChild(area)
            area.select()
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('Copy failed'))
            } catch (error) {
                reject(error)
            } finally {
                area.remove()
            }
        })
    }

    /*
     * Chart tooltips: any element with data-kis-tip='{"t": "Title", "r": [[color, label, value], ...]}'.
     * Built with textContent, so client names in the data are never parsed as HTML.
     * Delegated on document, so it keeps working after Livewire morphs.
     */
    let tip = null
    const tipEl = () => {
        if (!tip) {
            tip = document.createElement('div')
            tip.className = 'kis-tip'
            tip.setAttribute('role', 'tooltip')
            document.body.appendChild(tip)
        }
        return tip
    }
    const fillTip = (data) => {
        const el = tipEl()
        el.replaceChildren()
        const title = document.createElement('div')
        title.className = 'kis-tip__t'
        title.textContent = data.t ?? ''
        el.append(title)
        for (const [color, label, value] of data.r ?? []) {
            const row = document.createElement('div')
            row.className = 'kis-tip__r'
            const left = document.createElement('span')
            if (color) {
                const swatch = document.createElement('i')
                swatch.style.background = color
                left.append(swatch)
            }
            left.append(document.createTextNode(label))
            const strong = document.createElement('b')
            strong.textContent = value
            row.append(left, strong)
            el.append(row)
        }
    }
    document.addEventListener('mouseover', (event) => {
        const node = event.target.closest?.('[data-kis-tip]')
        if (!node) return
        try {
            fillTip(JSON.parse(node.dataset.kisTip))
        } catch {
            return
        }
        tipEl().classList.add('is-on')
    })
    document.addEventListener('mousemove', (event) => {
        if (!tip?.classList.contains('is-on')) return
        tip.style.left = Math.max(8, Math.min(event.clientX + 14, window.innerWidth - tip.offsetWidth - 8)) + 'px'
        tip.style.top = Math.max(8, event.clientY - tip.offsetHeight - 12) + 'px'
    })
    document.addEventListener('mouseout', (event) => {
        const node = event.target.closest?.('[data-kis-tip]')
        if (node && !node.contains(event.relatedTarget)) tip?.classList.remove('is-on')
    })
})()
