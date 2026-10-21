<?php

namespace Tests\Unit;

use App\Sending\Spintax;
use PHPUnit\Framework\TestCase;

class SpintaxTest extends TestCase
{
    public function test_each_group_resolves_to_one_of_its_options(): void
    {
        $result = Spintax::spin('{Hi|Hello|Hey} there, {friend|pal}!', 'seed-1');

        $this->assertMatchesRegularExpression('/^(Hi|Hello|Hey) there, (friend|pal)!$/', $result);
    }

    public function test_same_seed_gives_same_result_and_different_seeds_vary(): void
    {
        $text = '{a|b|c|d|e|f|g|h}';

        $this->assertSame(Spintax::spin($text, 'x'), Spintax::spin($text, 'x'));
        $results = array_unique(array_map(fn ($i) => Spintax::spin($text, "seed{$i}"), range(1, 40)));
        $this->assertGreaterThan(3, count($results));
    }

    public function test_nested_groups_are_expanded(): void
    {
        $result = Spintax::spin('{Great {deal|offer}|Big {sale|savings}}', 'n');

        $this->assertMatchesRegularExpression('/^(Great (deal|offer)|Big (sale|savings))$/', $result);
    }

    public function test_merge_tags_css_and_pipe_free_braces_are_untouched(): void
    {
        $html = '<style>.a{color:red}a[x|=y]{b:c|d}</style><p>{{ SUBSCRIBER_FIRST_NAME | there }} {x|x}</p><script>{"k":1}</script>';

        $result = Spintax::spin($html, 's');

        $this->assertStringContainsString('<style>.a{color:red}a[x|=y]{b:c|d}</style>', $result);
        $this->assertStringContainsString('{{ SUBSCRIBER_FIRST_NAME | there }} x', $result);
        $this->assertStringContainsString('{"k":1}', $result);
    }
}
