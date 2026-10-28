<script>
window.transferForm = function () {
    return {
        scan: '',
        results: [],
        lines: {},
        to_branch_id: '',
        notes: '',
        errors: {},
        busy: false,

        async lookup(productId = null) {
            this.errors = {};
            const query = productId ? 'product=' + productId : 'q=' + encodeURIComponent(this.scan.trim());
            if (! productId && ! this.scan.trim()) return;

            try {
                const res = await api('GET', @js(route('stock.receive.lookup')) + '?' + query);
                if (res.line) this.add(res.line);
                else this.results = res.results || [];
            } catch (e) {
                this.errors.scan = e.message;
            }
        },

        add(line) {
            const existing = this.lines[line.product_id];
            if (existing && existing.unit_id === (line.unit_id ?? '')) {
                existing.quantity = Number(existing.quantity) + Number(line.quantity);
            } else {
                this.lines[line.product_id] = {
                    product_id: line.product_id,
                    name: line.name,
                    units: line.units,
                    unit_id: line.unit_id ?? '',
                    quantity: line.quantity,
                };
            }
            this.scan = '';
            this.results = [];
            this.$nextTick(() => this.$refs.scan?.focus());
        },

        async send() {
            this.errors = {};
            this.busy = true;
            try {
                const res = await api('POST', @js(route('transfers.store')), {
                    to_branch_id: this.to_branch_id,
                    notes: this.notes,
                    lines: Object.fromEntries(Object.values(this.lines).map((l) => [l.product_id, {
                        product_id: l.product_id, unit_id: l.unit_id || null, quantity: l.quantity,
                    }])),
                });
                window.location = res.redirect;
            } catch (e) {
                const keys = Object.keys(e.errors || {});
                if (keys.length) keys.forEach((k) => this.errors[k] = e.errors[k][0]);
                else this.errors.general = e.message;
            } finally {
                this.busy = false;
            }
        },
    };
};
</script>
