<?php

namespace App\Services;

use App\Models\EmailAudienceGroup;
use App\Models\EmailContact;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Resolves an audience group (static list or dynamic filters) into an
 * EmailContact query. Shared by the group controller and campaign sending.
 */
class AudienceResolver
{
    public function query(EmailAudienceGroup $group): Builder
    {
        if ($group->type === 'static') {
            return $group->contacts()->getQuery();
        }

        return $this->applyFilters(EmailContact::query(), $group->filters ?? []);
    }

    /**
     * Apply dynamic filter rules to a contact query.
     * Supported keys: company, tag, tag_id, status, created_after,
     * created_before, activity (opened|clicked|none).
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['company'])) {
            $query->where('company', 'like', '%' . $filters['company'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tag_id'])) {
            $query->whereHas('tags', fn($q) => $q->where('email_tags.id', $filters['tag_id']));
        }

        if (!empty($filters['tag'])) {
            $query->whereHas('tags', fn($q) => $q->where('email_tags.name', $filters['tag']));
        }

        if (!empty($filters['created_after'])) {
            $query->whereDate('created_at', '>=', $filters['created_after']);
        }

        if (!empty($filters['created_before'])) {
            $query->whereDate('created_at', '<=', $filters['created_before']);
        }

        if (!empty($filters['activity'])) {
            match ($filters['activity']) {
                'opened'  => $query->whereHas('recipients', fn($q) => $q->whereIn('status', ['opened', 'clicked'])),
                'clicked' => $query->whereHas('recipients', fn($q) => $q->where('status', 'clicked')),
                'none'    => $query->whereDoesntHave('recipients'),
                default   => null,
            };
        }

        return $query;
    }
}
