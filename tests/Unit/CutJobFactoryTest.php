<?php

test('cut job factory generates ULID-format file paths', function () {
    $factory = \Database\Factories\CutJobFactory::new();
    $definition = $factory->definition();
    $filePath = $definition['file_path'];

    // Extract the ID segment from the path: users/1/jobs/{ID}/original.ext
    preg_match('/jobs\/([^\/]+)\/original/', $filePath, $matches);
    $idSegment = $matches[1];

    // ULID is 26 chars, uppercase alphanumeric (Crockford Base32)
    expect($idSegment)->toMatch('/^[0-9A-Z]{26}$/');
});
