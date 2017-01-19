<?php
/**
 * Created by Roquie.
 * E-mail: roquie0@gmail.com
 * GitHub: Roquie
 * Date: 18/01/2017
 */

namespace Roskomnadzor\Resolver;

use FastXml\CallbackHandler\GenericHandler;
use FastXml\Parser;
use Net_DNS2_RR_A;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MainCommand
 *
 * @package Roskomnadzor\Resolver
 */
class MainCommand extends Command
{
    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @var callable
     */
    protected $callbackWriter;

    /**
     * Configure this command.
     */
    protected function configure()
    {
        $path = __DIR__ . '/../';

        $this
            ->setName('dump:ip-list')
            ->setDescription('Создаёт файл со списком найденных IP-адресов.')
            ->addOption('log-disable', null, InputOption::VALUE_NONE, 'Отключает запись логов.')
            ->addOption('logfile', null, InputOption::VALUE_OPTIONAL, 'Путь до файла с логами.', $path. 'roskomnadzor.log')
            ->addOption(
                'no-progress', 'fast',
                InputOption::VALUE_NONE,
                'Убирает прогресс-бар. Из-за этого увеличится скорость обработки и уменьшится потребление памяти.'
            )
            ->addOption('resolve', 'r', InputOption::VALUE_NONE, 'Резолвить домены или нет.')
            ->addOption(
                'nameservers', null, InputOption::VALUE_OPTIONAL, //InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Указывается вместе с параметром --resolve, если надо.', '8.8.8.8'//['8.8.8.8', '8.8.4.4']
            )
            ->addOption('source', 's', InputOption::VALUE_OPTIONAL, 'Дамп роскомнадзора.', $path . 'dump.xml')
            ->addOption(
                'destination', 'd', InputOption::VALUE_OPTIONAL,
                'Куда и в какой файл положить список IP-адресов.', $path . 'iplist.txt'
            );
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source      = $input->getOption('source');
        $destination = $input->getOption('destination');

        // Handler by default
        $this->writeIpsWithoutResolvingHandler();

        if ($input->getOption('resolve')) {
            $this->writeIpsWithResolvingHandler($input, $output);
        }

        $this->runProcessing($input, $output, $source, $destination);

        $output->writeln(sprintf(
            '<comment>Complete. Total memory usage %s.</comment>',
            format_bytes(memory_get_usage(true))
        ));
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function writeIpsWithResolvingHandler(InputInterface $input, OutputInterface $output)
    {
        $net = new \Net_DNS2_Resolver(['nameservers' => explode(',', $input->getOption('nameservers'))]);

        $this->callbackWriter = function ($domain) use ($net, $input, $output) {
            if (valid_ip($domain)) {
                return $domain . PHP_EOL;
            }
            try {
                $results = $net->query($domain, 'A');
            } catch(\Exception $e) {
                $this->log(sprintf('%s - %s', $domain, $e->getMessage()), $input);
                return '';
            }

            $string = join(PHP_EOL, $this->readNetResolverARecords($results));

            if ((bool)$string) {
                return  $string . PHP_EOL;
            }

            return '';
        };
    }

    /**
     * @param string $string
     * @param \Symfony\Component\Console\Input\InputInterface $input
     */
    protected function log(string $string, InputInterface $input)
    {
        if ($input->getOption('log-disable')) {
            return;
        }

        file_put_contents($input->getOption('logfile'), date(DATE_ISO8601) . ' | ' . $string . PHP_EOL, FILE_APPEND);
    }

    /**
     * @param \Net_DNS2_Packet_Response $response
     * @return array
     */
    protected function readNetResolverARecords(\Net_DNS2_Packet_Response $response) : array
    {
        $array = [];
        foreach ($response->answer as $item) {
            if ($item instanceof Net_DNS2_RR_A) {
                $array[] = $item->address;
            }
        }

        return $array;
    }

    /**
     * @return void
     */
    protected function writeIpsWithoutResolvingHandler()
    {
        $this->callbackWriter = function ($ipAddress) {
            return $ipAddress . PHP_EOL;
        };
    }

    /**
     * @param $input
     * @param $output
     * @param $source
     * @param $destination
     */
    protected function runProcessing(InputInterface $input, OutputInterface $output, string $source, string $destination)
    {
        $tag = $input->getOption('resolve') ? 'domain' : 'ip';

        if ( ! $input->getOption('no-progress')) {
            $this->runParsingWithProgress($output, $source, $destination, $tag);
        } else {
            $this->justParseAndWrite($source, $destination, null, $tag);
        }
    }

    /**
     * @param $output
     * @param $source
     * @param $destination
     * @param string $endTag
     */
    protected function runParsingWithProgress($output, $source, $destination, string $endTag)
    {
        $progressBar = new ProgressBar($output, $this->getCountOfAllElements($source, $endTag));
        $progressBar->setRedrawFrequency(500);
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%' . PHP_EOL);
        $progressBar->start();
        $this->justParseAndWrite($source, $destination, function () use ($progressBar) {
            $progressBar->advance();
        }, $endTag);
        $progressBar->finish();
    }

    /**
     * @param string $source
     * @param string $destination
     * @param callable $callback
     * @param string $endTag
     */
    protected function justParseAndWrite(string $source, string $destination, callable $callback = null, string $endTag = 'domain')
    {
        $generic = new GenericHandler;
        $handle  = fopen($destination, 'w');

        $generic->setOnItemParsedCallback(function ($item) use ($handle, $endTag, $callback) {
            $this->callIfUnique($item[$endTag], function ($value) use ($handle) {
                fwrite($handle, ($this->callbackWriter)($value));
            });

            if (null !== $callback) {
                $callback($item);
            }
        });

        $parser = new Parser($generic);

        $parser->setIgnoreTags(['decision', 'url']);
        $parser->setEndTag($endTag);

        $parser->parse($source);
        fclose($handle);
    }

    /**
     * @param $value
     * @param callable $callback
     */
    protected function callIfUnique($value, callable $callback)
    {
        static $duplicates = null;
        if ( ! isset($duplicates[$value])) {
            $duplicates[$value] = 0;
        }

        if (($duplicates[$value]++) === 1) {
            $callback($value);
        }
    }

    /**
     * Подсчет кол-ва тегов <ip> (или <content>) в xml файле. Для больших файлов - атас!
     * Лучше использовать опцию --no-progress, если $source размером вышел.
     *
     * @param string $source
     * @param string $endTagName
     * @return int
     */
    protected function getCountOfAllElements(string $source, $endTagName = 'ip')
    {
        return substr_count(file_get_contents($source), sprintf('<%s', $endTagName));
    }
}