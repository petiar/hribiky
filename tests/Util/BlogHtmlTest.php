<?php

namespace App\Tests\Util;

use App\Entity\BlogPost;
use App\Util\BlogHtml;
use PHPUnit\Framework\TestCase;

class BlogHtmlTest extends TestCase
{
    public function testNonBreakingSpacesBecomeNormal(): void
    {
        $this->assertSame(
            '<p>Pri plánovaní trasy <strong>odporúčame</strong> mapy</p>',
            BlogHtml::clean("<p>Pri&nbsp;plánovaní&#160;trasy\u{00A0}&nbsp;<strong>odporúčame</strong>&NBSP;mapy</p>"),
        );
    }

    public function testEmptyListItemsAndParagraphsAreRemoved(): void
    {
        $this->assertSame(
            '<ul><li>Jedna</li><li>Dva</li></ul><p>Text</p>',
            BlogHtml::clean('<ul><li></li><li>Jedna</li><li> <br> </li><li>Dva</li><li><p></p></li></ul><p>&nbsp;</p><p>Text</p><p><br/></p>'),
        );
    }

    public function testCleanHtmlIsUnchanged(): void
    {
        $html = '<h2>Nadpis</h2><p>Odsek s <a href="https://mapy.cz">odkazom</a>.</p><ul><li>Bod</li></ul>';
        $this->assertSame($html, BlogHtml::clean($html));
    }

    public function testBlogPostSetTextCleans(): void
    {
        $this->assertSame('<p>a b</p>', (new BlogPost())->setText('<p>a&nbsp;b</p><li></li>')->getText());
    }
}
