<?php

function packageTranslations(string $locale): array
{
    return json_decode(file_get_contents(__DIR__.'/../../../lang/'.$locale.'.json'), true, flags: JSON_THROW_ON_ERROR);
}

test('the german translations have exactly the keys of the english ones', function () {
    expect(array_keys(packageTranslations('de')))->toEqualCanonicalizing(array_keys(packageTranslations('en')));
});

test('every translatable string used in the package source has an english and german translation', function () {
    $keys = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../../src')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $key) {
            $key = str_replace("\\'", "'", $key);

            if (str_contains($key, ' ') || ctype_upper($key[0] ?? '')) {
                $keys[$key] = true;
            }
        }
    }

    expect(array_diff(array_keys($keys), array_keys(packageTranslations('en'))))->toBe([])
        ->and(array_diff(array_keys($keys), array_keys(packageTranslations('de'))))->toBe([]);
});

test('the package ships german translations that the application translator uses', function () {
    app()->setLocale('de');

    expect(__('Impersonation failed'))->toBe('Anmeldung als Benutzer fehlgeschlagen');
});
