<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\User;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\ValueObjects\TrackableType;
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
     * Every field the request validates must be postable, otherwise a
     * required rule would make the form impossible to submit.
     */
    public function test_the_form_posts_every_field_the_request_validates(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();
        $response->assertSee('name="subject"', false);
        $response->assertSee('name="type"', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="trackable_type"', false);
        $response->assertSee('name="trackable_id"', false);
        $response->assertSee('name="due_date"', false);
        $response->assertSee('name="user_id"', false);
    }

    public function test_the_form_posts_to_the_store_route_with_a_csrf_token(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();
        $response->assertSee('action="'.route('admin.activities.store').'"', false);
        $response->assertSee('name="_token"', false);
    }

    /**
     * The type select is driven off the enum the request validates against,
     * so a new case cannot be accepted by the write path yet unselectable here.
     */
    public function test_the_type_select_lists_every_activity_type(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.create'));

        $response->assertOk();

        foreach (ActivityType::cases() as $activityType) {
            $response->assertSee('value="'.$activityType->value.'"', false);
        }
    }

    /**
     * A failed save redirects back with the input, so each field has to
     * repopulate from old() rather than rendering empty.
     */
    public function test_the_fields_repopulate_from_the_previous_input(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->withSession(['_old_input' => [
                'subject' => 'Discovery call',
                'type' => ActivityType::CALL->value,
                'trackable_type' => TrackableType::allowedTypes()[0],
                'trackable_id' => '42',
            ]])
            ->get(route('admin.activities.create'));

        $response->assertOk();
        $response->assertSee('value="Discovery call"', false);
        $response->assertSee('value="42"', false);
    }

    /**
     * A rejected save redirects back and the form re-renders from the shared
     * error bag, so the invalid markup has to actually reach the browser
     * rather than sitting in the template unexercised.
     */
    public function test_a_rejected_save_marks_the_failed_fields_invalid(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->from(route('admin.activities.create'))
            ->followingRedirects()
            ->post(route('admin.activities.store'), []);

        $response->assertOk();
        $response->assertSee('is-invalid', false);
        $response->assertSee('invalid-feedback', false);
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
            TrackableType::allowedTypes(),
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
