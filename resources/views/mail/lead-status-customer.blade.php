<x-mail::message>
# Thank you, {{ $lead->customer_name }}! ☀️

Thanks for your interest in going solar with {{ config('app.name') }}. We've received your details, and here's a quick update.

@switch($lead->status->value)
@case('qualified')
Great news — your property looks like an excellent fit for our solar leasing programme! One of our consultants will be in touch shortly to walk you through the next steps.
@break

@case('under_review')
Your submission looks promising, and our team is taking a closer look to put together the best possible option for you. We'll follow up very soon.
@break

@case('disqualified')
Based on the details you shared, your property isn't a match for our current solar leasing programme just yet. Eligibility and coverage are expanding all the time, so this may well change — and in the meantime our team would be glad to suggest other ways you could save on your energy costs.
@break
@endswitch

If you have any questions, simply reply to this email — we're always happy to help.

Warm regards,<br>
The {{ config('app.name') }} Team
</x-mail::message>
