export function connectionForProperty(propertyGroupId) {
    return {
        customFieldName: null,
        enabled: true,
        propertyGroupId: propertyGroupId || null,
        storage: 'property',
    };
}

export function connectionForProductDetails() {
    return {
        customFieldName: 'jv_catalog_attributes',
        enabled: true,
        propertyGroupId: null,
        storage: 'custom_field',
    };
}

export function disconnectedConnection() {
    return {
        customFieldName: null,
        enabled: false,
        propertyGroupId: null,
        storage: 'ignore',
    };
}

export function isConnected(mapping) {
    return mapping.enabled && ('custom_field' === mapping.storage || ('property' === mapping.storage && !!mapping.propertyGroupId));
}
