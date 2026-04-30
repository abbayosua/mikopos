// MIKO Pos - App shell
document.addEventListener('alpine:init', () => {
    // Global format money helper
    window.formatMoney = (n) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(n || 0);
    };
});
