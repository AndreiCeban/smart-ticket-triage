<?php


namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        $subjects = [
            'Cannot log into my account',
            'Password reset not working',
            'Billing inquiry about recent charge',
            'Feature request: dark mode',
            'Application crashes on startup',
            'Unable to upload files',
            'Email notifications not received',
            'Account locked after failed login attempts',
            'API rate limit exceeded',
            'Database connection timeout',
            'Payment processing error',
            'User interface bug in dashboard',
            'Mobile app not syncing',
            'Export function not working',
            'Two-factor authentication issues',
            'Slow page loading times',
            'Missing data in reports',
            'Integration with third-party service',
            'SSL certificate error',
            'Backup restoration request',
        ];

        $bodies = [
            'I\'ve been trying to log into my account for the past hour but keep getting an "Invalid credentials" error. I\'m sure my password is correct. Can you help?',
            'The password reset email never arrives in my inbox. I\'ve checked spam folder too. My email is working fine for other services.',
            'I noticed a charge on my account that I don\'t recognize. Can you provide details about what this charge is for?',
            'Would it be possible to add a dark mode option to the application? Many users have requested this feature.',
            'Every time I try to start the application, it crashes immediately. This started happening after the latest update.',
            'I\'m unable to upload files larger than 5MB. The upload progress bar gets stuck at 50% and then fails.',
            'I\'m not receiving any email notifications from the system, even though I have them enabled in my settings.',
            'My account has been locked due to too many failed login attempts. I need help unlocking it.',
            'I\'m getting rate limit errors when making API calls, but I\'m well within my quota. Can you investigate?',
            'The application shows database connection timeout errors intermittently. This affects multiple users.',
            'Payment processing is failing with error code 500. Customers cannot complete their purchases.',
            'There\'s a visual bug in the dashboard where buttons overlap with text. This happens on Chrome browser.',
            'The mobile app hasn\'t synced data for the past 3 days. All changes made on mobile are not reflected on web.',
            'The export to CSV function returns an empty file instead of the expected data.',
            'Two-factor authentication codes are not working. I\'ve tried multiple codes but none are accepted.',
            'Page loading times have increased significantly over the past week. Some pages take over 30 seconds to load.',
            'The monthly reports are missing data for the last 5 days. All other data appears correct.',
            'I need help setting up integration with our CRM system. Do you have documentation available?',
            'Users are seeing SSL certificate warnings when accessing the secure area of the site.',
            'We need to restore data from last week\'s backup due to accidental deletion. Please advise on the process.',
        ];

        $subject = $this->faker->randomElement($subjects);
        $body = $this->faker->randomElement($bodies);

        return [
            'subject' => $subject,
            'body' => $body,
            'status' => $this->faker->randomElement(array_keys(Ticket::STATUSES)),
        ];
    }

    public function withNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $this->faker->sentence(10),
        ]);
    }

    public function classified(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $this->faker->randomElement(array_keys(Ticket::CATEGORIES)),
            'confidence' => $this->faker->randomFloat(2, 0.6, 0.95),
            'explanation' => $this->faker->sentence(8),
        ]);
    }
}
