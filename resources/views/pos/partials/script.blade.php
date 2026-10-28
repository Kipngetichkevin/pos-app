<script>
window.posTerminal = function (config) {
    const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;
    const storageKey = 'pos-sale-' + config.shiftId;
    const emptyMpesa = () => ({ status: '', amount: '', phone: '', code: '', txId: null, message: '', acceptUnverified: false, sending: false });

    return {
        isManager: config.isManager,
        cart: {},
        scan: '',
        results: [],
        category: null,
        grid: [],
        picker: null,
        cash: '',
        credit: '',
        customer: null,
        customerSearch: '',
        customerResults: [],
        newCustomerPhone: '',
        mpesa: emptyMpesa(),
        recentPayments: [],
        errors: {},
        busy: false,
        lastSale: null,
        pollTimer: null,
        paying: false,

        init() {
            // A refresh or power cut must not lose the sale in progress
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (saved) {
                    this.cart = saved.cart || {};
                    this.customer = saved.customer || null;
                }
            } catch (e) {}

            this.$watch('cart', () => this.save());
            this.$watch('customer', () => this.save());

            this.loadGrid(null);
            this.loadRecentPayments();
            setInterval(() => {
                if (Object.keys(this.cart).length && ! this.mpesa.status) this.loadRecentPayments();
            }, 5000);

            this.focusScan();

            // F2 search, F9 charge / complete, Esc back
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F2') { e.preventDefault(); this.paying = false; this.focusScan(); }
                if (e.key === 'F9') { e.preventDefault(); this.paying ? this.checkout() : this.startPayment(); }
                if (e.key === 'Escape' && ! this.picker && ! this.lastSale) { this.paying = false; this.focusScan(); }
            });
        },

        get itemCount() {
            return Object.keys(this.cart).length;
        },

        startPayment() {
            if (! this.itemCount) return;
            this.paying = true;
            this.errors.checkout = null;
            this.$nextTick(() => this.$refs.cash?.focus());
        },

        step(key, delta) {
            const item = this.cart[key];
            if (! item) return;
            this.updateQuantity(key, Math.max(0, (Number(item.quantity) || 0) + delta));
        },

        // A soft colour per product, always the same for the same name
        tileColor(name) {
            const colors = ['bg-indigo-100 text-indigo-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-800',
                'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700', 'bg-teal-100 text-teal-700', 'bg-orange-100 text-orange-700'];
            let h = 0;
            for (const c of name) h = (h * 31 + c.charCodeAt(0)) >>> 0;
            return colors[h % colors.length];
        },

        initials(name) {
            return name.split(/[\s-]+/).filter((w) => /^[a-z]/i.test(w)).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
        },

        save() {
            try {
                localStorage.setItem(storageKey, JSON.stringify({ cart: this.cart, customer: this.customer }));
            } catch (e) {}
        },

        focusScan() {
            this.$nextTick(() => this.$refs.scan?.focus());
        },

        // Same rule as Product::priceFor() on the server
        isWholesale(item) {
            return item.wholesale_price !== null && item.wholesale_price !== undefined
                && (this.customer?.price_level === 'wholesale'
                    || (item.wholesale_min_qty !== null && item.wholesale_min_qty !== undefined && item.quantity >= item.wholesale_min_qty));
        },

        priceOf(item) {
            return this.isWholesale(item) ? item.wholesale_price : item.price;
        },

        get total() {
            return round2(Object.values(this.cart).reduce((sum, i) => sum + round2(this.priceOf(i) * i.quantity), 0));
        },

        get balance() {
            return round2(this.total - (Number(this.cash) || 0) - (Number(this.mpesa.amount) || 0) - (Number(this.credit) || 0));
        },

        // ---------------- Adding items ----------------

        async addScan() {
            const term = this.scan.trim();
            this.errors.scan = null;
            this.results = [];
            if (! term) return;

            try {
                const res = await api('GET', @js(route('pos.lookup')) + '?q=' + encodeURIComponent(term));
                if (res.add) this.addItem(res.add);
                else if (res.pick) { this.scan = ''; await this.pick(res.pick); }
                else this.results = res.results || [];
            } catch (e) {
                this.errors.scan = e.message;
            }
        },

        async pick(productId) {
            this.results = [];
            try {
                const res = await api('GET', @js(url('pos/products')) + '/' + productId);
                if (res.choices.length === 1) this.addItem(res.choices[0].item);
                else if (res.choices.length) this.picker = res;
            } catch (e) {
                this.errors.scan = e.message;
            }
        },

        addItem(item) {
            if (this.cart[item.key]) {
                this.cart[item.key].quantity = Math.round((this.cart[item.key].quantity + item.quantity) * 1000) / 1000;
            } else {
                this.cart[item.key] = { ...item };
            }
            this.scan = '';
            this.results = [];
            this.focusScan();
        },

        updateQuantity(key, value) {
            if (! this.cart[key]) return;
            let qty = Math.round((Number(value) || 0) * 1000) / 1000;
            if (! this.cart[key].decimal) qty = Math.floor(qty);
            if (qty <= 0) delete this.cart[key];
            else this.cart[key].quantity = qty;
        },

        removeItem(key) {
            delete this.cart[key];
            this.focusScan();
        },

        clearCart() {
            this.cart = {};
            this.results = [];
            this.scan = '';
            this.cash = '';
            this.credit = '';
            this.customer = null;
            this.customerSearch = '';
            this.newCustomerPhone = '';
            this.stopPolling();
            this.mpesa = emptyMpesa();
            this.errors = {};
            this.paying = false;
            this.focusScan();
        },

        async loadGrid(category) {
            this.category = category;
            try {
                this.grid = await api('GET', @js(route('pos.products')) + (category ? '?category=' + category : ''));
            } catch (e) {
                this.grid = [];
            }
        },

        // ---------------- Cash and credit ----------------

        cashExact() {
            this.cash = Math.max(round2(this.total - (Number(this.mpesa.amount) || 0) - (Number(this.credit) || 0)), 0);
        },

        creditBalance() {
            this.credit = Math.max(round2(this.total - (Number(this.cash) || 0) - (Number(this.mpesa.amount) || 0)), 0);
        },

        async searchCustomers() {
            if (this.customerSearch.trim().length < 2) { this.customerResults = []; return; }
            try {
                this.customerResults = await api('GET', @js(route('pos.customers')) + '?q=' + encodeURIComponent(this.customerSearch.trim()));
            } catch (e) {
                this.customerResults = [];
            }
        },

        selectCustomer(c) {
            this.customer = c;
            this.customerSearch = '';
            this.customerResults = [];
            this.errors.customer = null;
        },

        clearCustomer() {
            this.customer = null;
            this.credit = '';
            this.customerSearch = '';
            this.newCustomerPhone = '';
        },

        async quickAddCustomer() {
            this.errors.customer = null;
            try {
                const c = await api('POST', @js(route('pos.customers.store')), { name: this.customerSearch.trim(), phone: this.newCustomerPhone.trim() });
                this.selectCustomer(c);
                this.newCustomerPhone = '';
            } catch (e) {
                this.errors.customer = e.message;
            }
        },

        // ---------------- M-Pesa ----------------

        async sendStk() {
            this.errors.mpesaPhone = null;
            this.mpesa.message = '';

            const amount = Number(this.mpesa.amount) > 0
                ? Number(this.mpesa.amount)
                : round2(this.total - (Number(this.cash) || 0) - (Number(this.credit) || 0));

            if (! Object.keys(this.cart).length || amount <= 0) {
                this.errors.mpesaPhone = 'Add items first. There is nothing to pay by M-Pesa.';
                return;
            }

            this.mpesa.sending = true;
            try {
                const tx = await api('POST', @js(route('pos.mpesa.stk')), { phone: this.mpesa.phone, amount });
                this.mpesa.txId = tx.id;
                this.mpesa.status = 'pending';
                this.mpesa.amount = tx.amount;
                this.mpesa.code = '';
                this.pollTimer = setInterval(() => this.checkMpesa(), 2000);
            } catch (e) {
                this.errors.mpesaPhone = e.message;
            } finally {
                this.mpesa.sending = false;
            }
        },

        async checkMpesa() {
            if (this.mpesa.status !== 'pending' || ! this.mpesa.txId) return this.stopPolling();

            let res;
            try {
                res = await api('GET', @js(url('pos/mpesa')) + '/' + this.mpesa.txId);
            } catch (e) {
                return; // server busy or offline for a moment: keep waiting
            }

            if (res.status === 'success') {
                this.stopPolling();
                this.mpesa.status = 'success';
                this.mpesa.code = res.code;
                this.mpesa.amount = res.amount;
                this.mpesa.message = '';
            } else if (res.status !== 'pending') {
                this.stopPolling();
                this.mpesa.status = '';
                this.mpesa.txId = null;
                this.mpesa.message = res.message;
            }
        },

        stopPolling() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            this.pollTimer = null;
        },

        cancelMpesa() {
            this.stopPolling();
            this.mpesa = { ...emptyMpesa(), phone: this.mpesa.phone };
        },

        async loadRecentPayments() {
            try {
                this.recentPayments = await api('GET', @js(route('pos.mpesa.recent')));
            } catch (e) {}
        },

        usePayment(p) {
            this.stopPolling();
            this.mpesa = { ...emptyMpesa(), status: 'success', txId: p.id, code: p.code, amount: p.amount, phone: p.phone };
        },

        // ---------------- Checkout ----------------

        async checkout() {
            this.errors = {};

            if (! Object.keys(this.cart).length) {
                this.errors.checkout = 'The cart is empty.';
                return;
            }

            if (this.mpesa.status === 'pending') {
                this.errors.checkout = 'Wait for the M-Pesa payment to finish first.';
                return;
            }

            if (this.balance > 0) {
                this.errors.checkout = 'KSh ' + money(this.balance) + ' still to pay.';
                return;
            }

            if (this.busy) return;
            this.busy = true;
            try {
                this.lastSale = await api('POST', @js(route('pos.checkout')), {
                    items: Object.values(this.cart).map((i) => ({ product_id: i.product_id, unit_id: i.unit_id, quantity: i.quantity })),
                    cash: Number(this.cash) || 0,
                    mpesa_amount: Number(this.mpesa.amount) || 0,
                    mpesa_code: this.mpesa.code,
                    mpesa_transaction_id: this.mpesa.status === 'success' ? this.mpesa.txId : null,
                    accept_unverified: this.mpesa.acceptUnverified,
                    customer_id: this.customer?.id || null,
                    credit: Number(this.credit) || 0,
                });
                this.clearCart();
                this.$nextTick(() => this.$refs.newSale?.focus());
            } catch (e) {
                const field = Object.keys(e.errors || {})[0];
                this.errors[['mpesaCode', 'checkout'].includes(field) ? field : 'checkout'] = e.message;
            } finally {
                this.busy = false;
            }
        },

        newSale() {
            this.lastSale = null;
            this.focusScan();
        },
    };
};
</script>
