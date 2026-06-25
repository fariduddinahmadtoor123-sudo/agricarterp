<?php

namespace App\Services\ProductCatalog;

class ProductControlAssignmentService
{
    public function __construct(
        protected ProductControlGroupQuery $groupQuery,
        protected ProductControlQuery $controlQuery,
    ) {}

    /**
     * @param  list<int>  $groupIds
     * @param  list<int>  $individualControlIds
     * @return list<int>
     */
    public function deduplicatedIndividualControlIds(array $groupIds, array $individualControlIds): array
    {
        $fromGroups = $this->groupQuery->controlIdsFromGroups($groupIds);

        return array_values(array_filter(
            array_unique(array_map(fn ($id): int => (int) $id, $individualControlIds)),
            fn (int $controlId): bool => ! in_array($controlId, $fromGroups, true),
        ));
    }

    /**
     * @param  list<int>  $groupIds
     * @param  list<int>  $individualControlIds
     * @return list<string>
     */
    public function effectiveControlLabels(array $groupIds, array $individualControlIds): array
    {
        $controlIds = array_unique(array_merge(
            $this->groupQuery->controlIdsFromGroups($groupIds),
            $this->deduplicatedIndividualControlIds($groupIds, $individualControlIds),
        ));

        $labels = [];

        foreach ($controlIds as $controlId) {
            $label = $this->controlQuery->controlLabel($controlId);

            if ($label !== null) {
                $labels[] = $label;
            }
        }

        sort($labels);

        return $labels;
    }
}
