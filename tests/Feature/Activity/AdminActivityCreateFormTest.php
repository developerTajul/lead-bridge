<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\User;
use Tests\TestCase;

/**
 * Feature cover for the admin activity create form. The attach-to select is
 * driven by the record types an activity may be attached to, so an empty list
 * would silently leave the form unusable.
 *
 * The web middleware group is deliberately left in place: the form reads
 * $errors, which ShareErrorsFromSession provides, so stripping it would test
 * a request the application never serves.
 */
final class AdminActivityCreateFormTest extends TestCase
{

    public function test_the_route_resolves_and_renders(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();
        $response->assertViewIs('admin.activities.create');
        $response->assertViewHas('trackableTypes');
    }

    public function test_the_attach_to_select_is_populated(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();
        $response->assertSee('Attach To');
        $response->assertSee('Lead');
        $response->assertSee('Deal');
        $response->assertSee('Contact');
        $response->assertSee('Company');
    }

    public function test_every_option_carries_its_morph_alias_as_the_value(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();
        $response->assertSee('value="lead"', false);
        $response->assertSee('value="deal"', false);
        $response->assertSee('value="contact"', false);
        $response->assertSee('value="company"', false);
    }

    /**
     * The select is fed from the same allowlist the write path validates
     * against, so a record type added there cannot go missing from the form.
     */
    public function test_the_options_match_the_allowed_record_types(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();

        /** @var array<int, array{value: string, label: string}> $options */
        $options = $response->viewData('trackableTypes');

        $this->assertSame(
            \Core\Activity\Domain\ValueObjects\TrackableType::allowedTypes(),
            array_column($options, 'value'),
        );

        foreach ($options as $option) {
            $this->assertNotSame('', $option['label'], 'An option without a label renders blank.');
        }
    }

    private function makeUser(): User
    {
        return new User(['name' => 'Tajul', 'email' => 'tajul@example.com']);
    }
}
