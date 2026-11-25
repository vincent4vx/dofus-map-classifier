<?php

declare(strict_types=1);

namespace MapClassifier\Sprite\Command;

use MapClassifier\Sprite\Model\SpriteProfile;
use MapClassifier\Sprite\Repository\SpriteProfileRepository;
use MapClassifier\Sprite\Service\ShoreExampleRenderer;
use MapClassifier\Sprite\Shore\ShoreRole;
use MapClassifier\Sprite\Shore\ShoreRoleClassifier;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_fill_keys;
use function array_intersect_key;
use function array_values;
use function count;
use function explode;
use function implode;
use function max;
use function range;
use function sprintf;
use function str_contains;

#[AsCommand('shores:classify', 'Find the role of the shore pieces in the shores with the Responses API, from their example along the water')]
final class ShoresClassifyCommand extends Command
{
    public function __construct(
        private readonly SpriteProfileRepository $profiles,
        private readonly ShoreRoleClassifier $classifier,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY, 'Object ids or ranges (374-383). All the shore pieces by default')
            ->addOption('concurrency', null, InputOption::VALUE_REQUIRED, 'Concurrent requests', '5')
        ;
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $pieces = ShoreExampleRenderer::shorePieces($this->profiles->all());
        } catch (RuntimeException $e) {
            $io->error($e->getMessage() . ': run sprites:analyze first');

            return self::FAILURE;
        }

        $selected = [];

        foreach ((array) $input->getArgument('ids') as $id) {
            [$from, $to] = str_contains((string) $id, '-') ? explode('-', (string) $id, 2) : [$id, $id];
            $selected += array_fill_keys(range((int) $from, (int) $to), true);
        }

        $pieces = array_values($selected === [] ? $pieces : array_intersect_key($pieces, $selected));
        $classified = 0;
        $this->classifier->classify($pieces, max(1, (int) $input->getOption('concurrency')), static function (SpriteProfile $profile, ?ShoreRole $role, ?string $error) use ($io, &$classified) {
            $classified += $role !== null ? 1 : 0;
            $io->text($role !== null
                ? sprintf('o%d: %s %s, water %s, land %s, continues %s: %s', $profile->id, $role->kind, $role->shape, implode(' ', $role->water), implode(' ', $role->land), implode(' ', $role->continues), $role->description)
                : sprintf('o%d: %s', $profile->id, $error));
        });

        if ($classified < count($pieces)) {
            $io->error(sprintf('%d of %d shore pieces classified', $classified, count($pieces)));

            return self::FAILURE;
        }

        $io->success(sprintf('%d shore pieces classified', $classified));

        return self::SUCCESS;
    }
}
