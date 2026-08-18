import test from 'node:test';
import assert from 'node:assert/strict';

import {
    connectionForProductDetails,
    connectionForProperty,
    disconnectedConnection,
    isConnected,
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
