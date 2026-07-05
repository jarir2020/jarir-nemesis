<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Nemesis\Console\Command;
use Nemesis\Console\VendorCompressor;

class VendorCompressCommand extends Command
{
    protected string $signature = 'vendor:compress {--dry-run} {--json} {--report=} {--keep=} {--exclude=} {--archive=} {--restore=}';
    protected string $description = 'Archive or remove unused vendor class files safely.';

    public function handle(): int
    {
        try {
            $compressor = new VendorCompressor(base_path());
            $reportPath = $this->input->option('report');
            $archivePath = $this->input->option('archive');
            $keep = $this->input->option('keep');
            $exclude = $this->input->option('exclude');
            $restore = $this->input->option('restore');
            $json = (bool) $this->input->option('json', false);

            if ($restore !== null && $restore !== false && $restore !== true) {
                $report = $compressor->restore((string) $restore);
            } elseif ($restore === true) {
                throw new \RuntimeException('The --restore option expects a file path.');
            } else {
                $report = $compressor->compress([
                    'dry_run' => $this->input->hasOption('dry-run'),
                    'json' => $json,
                    'report' => $reportPath,
                    'keep' => $keep,
                    'exclude' => $exclude,
                    'archive' => $archivePath,
                ]);
            }

            if ($json) {
                echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
                return self::SUCCESS;
            }

            $this->output->info('vendor:compress completed.');
            $this->output->line('Mode: ' . ($report['mode'] ?? 'unknown'));
            $this->output->line('Preserved: ' . (string) ($report['summary']['preserved'] ?? 0));
            $this->output->line('Candidates: ' . (string) ($report['summary']['candidates'] ?? 0));
            $this->output->line('Removed: ' . (string) ($report['summary']['removed'] ?? 0));
            $this->output->line('Restored: ' . (string) ($report['summary']['restored'] ?? 0));

            if (!empty($report['restore']['archive_path'])) {
                $this->output->line('Archive: ' . $report['restore']['archive_path']);
            }
            if (!empty($report['restore']['manifest_path'])) {
                $this->output->line('Manifest: ' . $report['restore']['manifest_path']);
            }

            foreach (($report['warnings'] ?? []) as $warning) {
                $this->output->warn((string) $warning);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->output->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
