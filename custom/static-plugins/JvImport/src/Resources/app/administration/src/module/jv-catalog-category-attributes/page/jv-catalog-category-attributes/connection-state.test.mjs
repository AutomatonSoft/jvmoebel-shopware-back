import test from 'node:test';
import assert from 'node:assert/strict';

import {
    availableAttributes,
    connectionForProductDetails,
    connectionForProperty,
    disconnectedConnection,
    isConnected,
    usedAttributes,
} from './connection-state.mjs';

test('a property connection is active only with a selected property group', () => {
    assert.deepEqual(connectionForProperty('a'.repeat(32)), {
        customFieldName: null,
        enabled: true,
        propertyGroupId: 'a'.repeat(32),
        storage: 'property',
    });
    assert.equal(isConnected(connectionForProperty('a'.repeat(32))), true);
    assert.equal(isConnected(connectionForProperty(null)), false);
});

test('a product-details connection does not retain a property group', () => {
    assert.deepEqual(connectionForProductDetails(), {
        customFieldName: 'jv_catalog_attributes',
        enabled: true,
        propertyGroupId: null,
        storage: 'custom_field',
    });
});

test('disconnecting clears every target and prevents product enrichment', () => {
    assert.deepEqual(disconnectedConnection(), {
        customFieldName: null,
        enabled: false,
        propertyGroupId: null,
        storage: 'ignore',
    });
    assert.equal(isConnected(disconnectedConnection()), false);
});

test('only connected source attributes are shown as category attributes', () => {
    const property = { id: 'property', active: true, ...connectionForProperty('a'.repeat(32)) };
    const productInformation = { id: 'information', active: true, ...connectionForProductDetails() };
    const unused = { id: 'unused', active: true, ...disconnectedConnection() };

    assert.deepEqual(usedAttributes([property, productInformation, unused]), [property, productInformation]);
});

test('only active unused source attributes can be added to a category', () => {
    const available = { id: 'available', active: true, ...disconnectedConnection() };
    const used = { id: 'used', active: true, ...connectionForProductDetails() };
    const removedFromSnapshot = { id: 'removed', active: false, ...disconnectedConnection() };

    assert.deepEqual(availableAttributes([available, used, removedFromSnapshot]), [available]);
});
