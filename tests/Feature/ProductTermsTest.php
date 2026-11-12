<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Product names are spelled one way (config/copy.php): no "challenge", "goalleague" or "matchup" written as a product
 * name in a button, link, heading, label or page title.
 */
class ProductTermsTest extends TestCase
{
    public function test_buttons_links_and_headings_spell_product_names_the_goalline_way(): void
    {
        $terms = collect(config('copy.terms'));
        $problems = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = $file->getContents();
            // Visible text in buttons, links, headings, legends and summaries, plus page titles and labels in props.
            preg_match_all('/<(button|a|h[1-6]|legend|summary|label)\b[^>]*>(.*?)<\/\1>/s', $source, $tags);
            preg_match_all('/(?<![:\w-])(?:title|action|label|submit)="([^"{$]*)"/', $source, $props);

            foreach (array_merge($tags[2], $props[1]) as $text) {
                $text = strip_tags(preg_replace(['/\{\{.*?\}\}|@\w+(\(.*?\))?/s', '/<[^>]*>/s', '/\$\w+(->\w+|\[[^\]]*\])*/', '/#[\w-]+/', '/\b[\w-]+="[^"]*"/', '/\[[^\]]*=>[^\]]*\]/s'], ' ', $text));

                foreach ($terms as $term) {
                    // The same word in the wrong case ("challenge", "Goalleague", "help center"), as a whole word.
                    if (preg_match_all('/\b'.preg_quote($term, '/').'\b/i', $text, $found)) {
                        foreach ($found[0] as $word) {
                            // "matchup stars" is the scoring term; "stars" and ordinary words are not product names.
                            if ($word !== $term && ! preg_match('/'.preg_quote($word, '/').' stars?/', $text) && ! in_array($word, ['stats', 'highlights', 'standings'], true)) {
                                $problems[] = $file->getRelativePathname().': "'.$word.'" should be "'.$term.'" in: '.trim(preg_replace('/\s+/', ' ', $text));
                            }
                        }
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($problems)));
    }

    public function test_no_lowercase_challenge_in_visible_text(): void
    {
        $problems = collect(File::allFiles(resource_path('views')))
            ->flatMap(fn ($file) => collect(explode("\n", $file->getContents()))
                ->map(fn ($line, $number) => [$file->getRelativePathname().':'.($number + 1), $line]))
            ->filter(function (array $row) {
                // Text between tags, and quoted text outside of code.
                $visible = preg_replace(['/\{\{--.*?--\}\}/', '/\{\{.*?\}\}/', '/<[^>]*>/'], ' ', $row[1]);

                return preg_match('/(?<![\w$>.\'"_-])(a|the|this|your|new|start a|join a|or|and|no) challenges?\b/i', $visible) === 1
                    && preg_match('/\b(a|the|this|your|new|start a|join a|or|and|no) Challenges?\b/i', $visible) !== 1;
            })
            ->map(fn (array $row) => $row[0].': '.trim($row[1]))
            ->values()
            ->all();

        $this->assertSame([], $problems);
    }
}
