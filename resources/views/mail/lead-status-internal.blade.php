<x-mail::message>
# New Lead — {{ $lead->status->value }}

A new lead has been ingested and automatically qualified.

<x-mail::table>
| Field         | Value                              |
| :------------ | :--------------------------------- |
| Name          | {{ $lead->customer_name }}         |
| Email         | {{ $lead->email }}                 |
| Phone         | {{ $lead->phone }}                 |
| Monthly bill  | RM{{ $lead->monthly_bill_rm }}     |
| Property type | {{ $lead->property_type->value }}  |
| Roof type     | {{ $lead->roof_type->value }}      |
| State         | {{ $lead->state->value }}          |
| **Status**    | **{{ $lead->status->value }}**     |
</x-mail::table>

<x-mail::button :url="config('app.url').'/admin'">
Open in dashboard
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
