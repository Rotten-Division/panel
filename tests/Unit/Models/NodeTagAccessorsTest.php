<?php

use App\Models\Node;

test('Node::locationCity returns the value after loc:', function () {
    $node = new Node();
    $node->tags = ['loc:london', 'cc:gb'];

    expect($node->locationCity)->toBe('london');
});

test('Node::locationCountryCode returns the value after cc:', function () {
    $node = new Node();
    $node->tags = ['loc:london', 'cc:gb'];

    expect($node->locationCountryCode)->toBe('gb');
});

test('Node::locationCity returns null when no loc tag is set', function () {
    $node = new Node();
    $node->tags = ['cc:gb'];

    expect($node->locationCity)->toBeNull();
});

test('Node::locationCountryCode returns null when no cc tag is set', function () {
    $node = new Node();
    $node->tags = ['loc:london'];

    expect($node->locationCountryCode)->toBeNull();
});

test('Node tag accessors tolerate null tags column', function () {
    $node = new Node();
    $node->tags = null;

    expect($node->locationCity)->toBeNull();
    expect($node->locationCountryCode)->toBeNull();
});
