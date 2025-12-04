<?php

declare(strict_types=1);

namespace App\Mcp\Prism\Tools\Funnel;

use App\Actions\Tenants\SwitchTenantContextAction;
use App\Repositories\Tenant\FunnelRepository;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Tool;

/**
 * Get Funnel Tool
 *
 * Retrieves detailed information about a specific funnel by its ID.
 * This tool handles tenant context switching and uses the FunnelRepository
 * for data access.
 */
class GetFunnelTool extends Tool
{
    public function __construct(
        private readonly int $accountId,
    ) {
        $this
            ->as('get_funnel')
            ->for('Get details about a specific funnel by ID')
            ->withObjectParameter(
                name: 'input',
                description: 'Funnel lookup parameters',
                properties: [
                    new NumberSchema('funnel_id', 'The ID of the funnel to fetch'),
                ],
                requiredFields: ['funnel_id']
            )
            ->using($this);
    }

    /**
     * Execute the tool to retrieve funnel information.
     *
     * @param  array{funnel_id: int|string}  $input
     * @return string JSON-encoded funnel data or error message
     */
    public function __invoke(array $input): string
    {
        SwitchTenantContextAction::run($this->accountId);

        $funnelId = (int) $input['funnel_id'];
        $funnelRepository = app(FunnelRepository::class);
        $funnel = $funnelRepository->findById($funnelId);

        if (empty($funnel)) {
            return json_encode([
                'error' => 'Funnel not found',
                'funnel_id' => $funnelId,
            ]);
        }

        return json_encode($funnel->toArray());
    }
}
