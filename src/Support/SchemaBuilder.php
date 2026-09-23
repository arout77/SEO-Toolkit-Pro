<?php

namespace Arout\SeoToolkit\Support;

/**
 * Static builders that return correctly-shaped schema.org arrays, ready to
 * hand straight to Rhapsody's own $this->schema->add($type, $data).
 *
 * This class does NOT go through ModuleContext, isn't gated by any
 * permission, and doesn't require the module to even be installed/activated
 * — it's a plain Composer-autoloaded utility. Its whole value is encoding
 * schema.org's fiddlier required nesting (Offer under Product, AggregateRating
 * shape, etc.) once, correctly, so callers don't have to look it up or get it
 * wrong by hand. $this->schema->add() can already accept any shape you build
 * yourself; this is a convenience layer on top of it, nothing more.
 *
 * Example usage in an app controller:
 *
 *   use Arout\SeoToolkit\Support\SchemaBuilder;
 *
 *   $this->schema->add('Product', SchemaBuilder::product(
 *       name: $item->getTitle(),
 *       description: $item->getDescription(),
 *       image: $item->getImageUrl(),
 *       price: $item->getPrice(),
 *       currency: 'USD',
 *       ratingValue: $item->getAverageRating(),
 *       ratingCount: $item->getReviewCount(),
 *   ));
 */
final class SchemaBuilder
{
    /**
     * @param array<int, array{name: string, url: string}> $items Ordered, root-first.
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        $listItems = [];
        foreach ($items as $index => $item) {
            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }

        return [
            'itemListElement' => $listItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function article(
        string $headline,
        string $image,
        string $authorName,
        string $datePublished,
        ?string $dateModified = null,
        ?string $description = null,
    ): array {
        $data = [
            'headline'      => $headline,
            'image'         => $image,
            'datePublished' => $datePublished,
            'dateModified'  => $dateModified ?? $datePublished,
            'author'        => [
                '@type' => 'Person',
                'name'  => $authorName,
            ],
        ];

        if ($description !== null) {
            $data['description'] = $description;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(
        string $name,
        string $description,
        string $image,
        float $price,
        string $currency = 'USD',
        string $availability = 'https://schema.org/InStock',
        ?float $ratingValue = null,
        ?int $ratingCount = null,
    ): array {
        $data = [
            'name'        => $name,
            'description' => $description,
            'image'       => $image,
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => number_format($price, 2, '.', ''),
                'priceCurrency' => $currency,
                'availability'  => $availability,
            ],
        ];

        if ($ratingValue !== null && $ratingCount !== null) {
            $data['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $ratingValue,
                'reviewCount' => $ratingCount,
            ];
        }

        return $data;
    }

    /**
     * @param array<int, array{question: string, answer: string}> $items
     * @return array<string, mixed>
     */
    public static function faqPage(array $items): array
    {
        $mainEntity = [];
        foreach ($items as $item) {
            $mainEntity[] = [
                '@type'          => 'Question',
                'name'           => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['answer'],
                ],
            ];
        }

        return [
            'mainEntity' => $mainEntity,
        ];
    }
}
