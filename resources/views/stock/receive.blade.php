@extends('layouts.app')

@section('title', 'Receive delivery')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6" x-data="receiveScreen()">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Receive delivery</h2>
        <a href="{{ route('stock.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Stock</a>
    </div>

    @include('partials.flash')

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @include('stock.partials.receive-scan')
            @include('stock.partials.receive-lines')
        </div>

        <div>
            @include('stock.partials.receive-details')
        </div>
    </div>

    @include('stock.partials.receive-recent')
</main>
@endsection

@push('scripts')
<script>
    window.receiveScreen = () => ({
        lookupUrl: @js(route('stock.receive.lookup')),
        saveUrl: @js(route('stock.receive.store')),
        scan: '',
        scanError: '',
        results: [],
        lines: [],
        supplier_id: '',
        newSupplier: '',
        invoice_no: '',
        updateCostPrice: true,
        errors: {},
        generalError: '',
        saving: false,

        init() {
            this.$nextTick(() => this.$refs.scan.focus());
        },

        fq(v) {
            return Number(v || 0).toLocaleString('en-US', { maximumFractionDigits: 3 });
        },

        pct(v) {
            return Number(v).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        },

        round2(v) {
            return Math.round((Number(v) || 0) * 100) / 100;
        },

        async addScan() {
            const term = this.scan.trim();
            this.scanError = '';
            this.results = [];

            if (term === '') return;

            await this.fetchLine('?q=' + encodeURIComponent(term));
        },

        async pick(id) {
            this.scanError = '';
            await this.fetchLine('?product=' + encodeURIComponent(id));
        },

        async fetchLine(query) {
            try {
                const data = await window.api('GET', this.lookupUrl + query);

                if (data.line) {
                    this.addLine(data.line);
                } else {
                    this.results = data.results || [];
                }
            } catch (e) {
                this.scanError = e.message;
            }
        },

        addLine(incoming) {
            const existing = this.lines.find((l) => l.product_id === incoming.product_id);

            if (existing) {
                const qty = Number(existing.quantity) + Number(incoming.quantity);
                existing.quantity = qty;
                existing.line_total = this.round2(qty * Number(existing.per) * Number(existing.cost_price));
            } else {
                this.lines.push(incoming);
            }

            this.scan = '';
            this.results = [];
            this.$nextTick(() => this.$refs.scan.focus());
        },

        setQuantity(line, value) {
            line.quantity = value;
            line.line_total = this.round2(Number(value) * Number(line.per) * Number(line.cost_price));
        },

        setUnit(line, unitId) {
            const chosen = line.units.find((u) => u.id === unitId);

            if (chosen) {
                line.unit_id = chosen.id;
                line.per = chosen.per;
            }
        },

        removeLine(line) {
            this.lines = this.lines.filter((l) => l.product_id !== line.product_id);
        },

        calc(line) {
            const qty = Number(line.quantity) || 0;
            const total = Number(line.line_total) || 0;
            const baseQty = qty * Number(line.per);
            const perBase = baseQty > 0 ? total / baseQty : 0;
            const sell = Number(line.selling_price);

            return {
                baseQty,
                perBase,
                perUnit: qty > 0 ? total / qty : 0,
                sell,
                margin: sell > 0 ? ((sell - perBase) / sell) * 100 : null,
            };
        },

        get total() {
            return this.round2(this.lines.reduce((sum, l) => sum + (Number(l.line_total) || 0), 0));
        },

        lineError(line, field) {
            return this.errors['lines.' + line.product_id + '.' + field]?.[0] || '';
        },

        async save() {
            if (!this.lines.length || this.saving) return;
            if (!confirm('Save this delivery and add the stock?')) return;

            this.saving = true;
            this.errors = {};
            this.generalError = '';

            const lines = {};
            this.lines.forEach((l) => {
                lines[l.product_id] = { product_id: l.product_id, unit_id: l.unit_id, quantity: l.quantity, line_total: l.line_total };
            });

            try {
                const data = await window.api('POST', this.saveUrl, {
                    lines,
                    supplier_id: this.supplier_id || null,
                    newSupplier: this.newSupplier,
                    invoice_no: this.invoice_no,
                    updateCostPrice: this.updateCostPrice,
                });

                window.location = data.redirect;
            } catch (e) {
                this.errors = e.errors || {};
                const lineKeys = Object.keys(this.errors).filter((k) => k.startsWith('lines.') && k.split('.').length === 3);
                this.generalError = lineKeys.length && lineKeys.length === Object.keys(this.errors).length ? '' : e.message;
                this.saving = false;
            }
        },
    });
</script>
@endpush
