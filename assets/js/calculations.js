function nivisMoney(value) { return "Rs " + Number(value).toLocaleString("en-IN", {minimumFractionDigits:2,maximumFractionDigits:2}); }
function nivisAmounts(quantity, price, discount, rate, mode = "exclusive") {
    const round = value => Math.round((value + Number.EPSILON) * 100) / 100;
    const base = round(quantity * price);
    const discountAmount = round(base * discount / 100);
    const net = round(base - discountAmount);
    const taxable = mode === "inclusive" ? round(net / (1 + rate / 100)) : net;
    const tax = mode === "inclusive" ? round(net - taxable) : round(taxable * rate / 100);
    const cgst = round(tax / 2);
    return {base, discount:discountAmount, taxable, tax, cgst, sgst:round(tax-cgst), total:round(taxable+tax)};
}
