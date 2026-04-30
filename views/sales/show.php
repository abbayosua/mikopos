<div x-data="saleDetail()" x-init="load(<?= $saleId ?? 0 ?>)">
    <div class="mb-4">
        <a href="/sales" class="text-indigo-600 hover:underline text-sm"><i class="fas fa-arrow-left mr-1"></i> Back to Sales</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold mb-4">Sale Details</h2>

                <template x-if="sale">
                    <div>
                        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                            <div>
                                <p class="text-gray-500">Invoice</p>
                                <p class="font-medium" x-text="sale.invoice_no"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Date</p>
                                <p class="font-medium" x-text="sale.created_at"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Cashier</p>
                                <p class="font-medium" x-text="sale.cashier_name || '-'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Customer</p>
                                <p class="font-medium" x-text="sale.customer_name || 'Walk-in'"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Payment Method</p>
                                <p class="font-medium capitalize" x-text="sale.payment_method"></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Status</p>
                                <span :class="sale.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 rounded-full text-xs capitalize" x-text="sale.status"></span>
                            </div>
                        </div>

                        <h3 class="font-semibold mb-2">Items</h3>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-2">Product</th>
                                    <th class="pb-2">Price</th>
                                    <th class="pb-2">Qty</th>
                                    <th class="pb-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in sale.items" :key="item.id">
                                    <tr class="border-b">
                                        <td class="py-2" x-text="item.product_name"></td>
                                        <td class="py-2" x-text="formatMoney(item.price)"></td>
                                        <td class="py-2" x-text="item.quantity"></td>
                                        <td class="py-2 text-right" x-text="formatMoney(item.subtotal)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <div class="mt-4 space-y-1 text-sm border-t pt-3">
                            <div class="flex justify-between"><span>Subtotal</span><span x-text="formatMoney(sale.subtotal)"></span></div>
                            <div class="flex justify-between"><span>Discount</span><span x-text="formatMoney(sale.discount)"></span></div>
                            <div class="flex justify-between"><span>Tax</span><span x-text="formatMoney(sale.tax)"></span></div>
                            <div class="flex justify-between font-bold text-lg"><span>Total</span><span x-text="formatMoney(sale.total)"></span></div>
                            <div class="flex justify-between"><span>Paid</span><span x-text="formatMoney(sale.amount_paid)"></span></div>
                            <div class="flex justify-between" x-show="sale.change_amount > 0"><span>Change</span><span x-text="formatMoney(sale.change_amount)"></span></div>
                        </div>

                        <div class="mt-4 flex gap-2" x-show="sale.status === 'completed'">
                            <button @click="voidSale(sale.id)" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">Void Sale</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-4">Receipt</h3>
                <div id="receipt" class="text-sm">
                    <template x-if="sale">
                        <div>
                            <div class="text-center mb-3">
                                <p class="font-bold text-lg" x-text="sale.tenant_name || 'MIKO Pos'"></p>
                                <p x-text="sale.tenant_address"></p>
                                <p x-text="sale.tenant_phone"></p>
                            </div>
                            <div class="border-t border-b py-2 mb-2">
                                <p>Invoice: <span x-text="sale.invoice_no"></span></p>
                                <p>Date: <span x-text="sale.created_at"></span></p>
                                <p>Cashier: <span x-text="sale.cashier_name"></span></p>
                                <template x-if="sale.customer_name">
                                    <p>Customer: <span x-text="sale.customer_name"></span></p>
                                </template>
                            </div>
                            <table class="w-full mb-2">
                                <template x-for="item in sale.items" :key="item.id">
                                    <tr>
                                        <td x-text="item.product_name + ' x' + item.quantity"></td>
                                        <td class="text-right" x-text="formatMoney(item.subtotal)"></td>
                                    </tr>
                                </template>
                            </table>
                            <div class="border-t pt-1">
                                <div class="flex justify-between font-bold"><span>Total</span><span x-text="formatMoney(sale.total)"></span></div>
                            </div>
                        </div>
                    </template>
                </div>
                <button @click="printReceipt()" class="mt-4 w-full bg-gray-200 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-300">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function saleDetail() {
    return {
        sale: null,
        async load(id) {
            const res = await fetch('/api/sales/' + id);
            const data = await res.json();
            if (data.success) this.sale = data.data;
        },
        async voidSale(id) {
            if (!confirm('Void this sale? This will restore stock.')) return;
            const res = await fetch('/api/sales/' + id, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) window.location.reload();
        },
        printReceipt() {
            window.print();
        },
        formatMoney(n) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(n || 0); }
    }
}
</script>
