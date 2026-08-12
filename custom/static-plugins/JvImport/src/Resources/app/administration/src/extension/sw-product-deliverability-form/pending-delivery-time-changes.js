const pendingChanges = new Map();

export function pendingDeliveryTimeChange(productId, salesChannelId) {
    return pendingChanges.get(productId)?.get(salesChannelId) ?? null;
}

export function pendingDeliveryTimeChanges(productId) {
    return [...(pendingChanges.get(productId)?.values() ?? [])];
}

export function stageDeliveryTimeChange(change) {
    const productChanges = pendingChanges.get(change.productId) ?? new Map();
    productChanges.set(change.salesChannelId, change);
    pendingChanges.set(change.productId, productChanges);
}

export function discardDeliveryTimeChanges(productId) {
    pendingChanges.delete(productId);
}
