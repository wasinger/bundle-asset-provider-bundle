<?php
namespace Wasinger\BundleAssetProviderBundle\Command;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 *
 * @author Christoph Singer <singer@webagentur72.de>
 *
 */
class CheckAssetDependenciesCommand extends Command
{
    protected static $defaultName = 'assets:dependencies';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('check whether npm packages required from bundles are there')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> searches all installed bundles for package.json files
and checks whether the dependencies are met in the project's package.json file.
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->newLine();
        $kernel = $this->getApplication()->getKernel();
        $package_json = $kernel->getContainer()->getParameter('kernel.project_dir').'/package.json';
        if (!\file_exists($package_json)) {
            $io->warning('There is no package.json in the project. Nothing to do.');
            $io->newLine();
            return 0;
        }

        $json = \json_decode(\file_get_contents($package_json), true);
        $project_deps = $json['dependencies'];
        $project_depnames = \array_keys($project_deps);

        $versionparser = new VersionParser();
        $project_constraints = [];
        foreach ($project_depnames as $dep_name) {
            try {
                $project_constraints[$dep_name] = $versionparser->parseConstraints($project_deps[$dep_name]);
            } catch (\Exception $e) {
                // ignore
            }
        }
        $rows = [];
        $exitCode = 0;
        $foundPackage = false;
        /** @var BundleInterface $bundle */
        foreach ($kernel->getBundles() as $bundle) {
            if (!\file_exists($package_json = $bundle->getPath().'/package.json')) {
                continue;
            }

            $bundle_json = \json_decode(\file_get_contents($package_json), true);
            $bundle_deps = $bundle_json['dependencies'];

            $message = $bundle->getName();

            foreach ($bundle_deps as $dep_name => $dep_version) {
                if (!in_array($dep_name, $project_depnames)) {
                    $exitCode = 1;
                    $rows[] = array(sprintf('<fg=red;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'MISSING' : "\xE2\x9C\x98" /* HEAVY BALLOT X (U+2718) */), $message, $dep_name, $dep_version, '<fg=red;>Missing</>');
                } else {
                    $foundPackage = true;
                    $constraint = $versionparser->parseConstraints($dep_version);
                    if (!$project_constraints[$dep_name]->matches($constraint)) {
                        $exitCode = 2;
                        $rows[] = array(
                            sprintf('<fg=red;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'MISMATCH' : "!"),
                            $message,
                            $dep_name,
                            $dep_version,
                            sprintf('<fg=red;>%s</>', $project_deps[$dep_name])
                        );
                    } else {
                        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                            $rows[] = array(
                                sprintf('<fg=green;options=bold>%s</>',
                                    '\\' === \DIRECTORY_SEPARATOR ? 'OK' : "\xE2\x9C\x94" /* HEAVY CHECK MARK (U+2714) */),
                                $message,
                                $dep_name,
                                $dep_version,
                                $project_deps[$dep_name]
                            );
                        }
                    }
                }
            }
        }

        if ($rows) {
            $io->table(array('', 'Bundle', 'NPM Package', 'req. Version', 'Project version'), $rows);
        }

        if (0 !== $exitCode) {
            $io->error('Some dependency requirements are not met.');
        } else {
            $io->success($foundPackage ? 'All required dependencies are listed in your package.json.' : 'No package.json was provided by any bundle.');
        }

        return $exitCode;
    }
}
