<?php
/**
 * IPTV 组播源扫描测速工具 - 高性能优化版
 * 优化点：测速并行化、城市级多进程并行、连接复用、智能超时
 */

set_time_limit(0);
ini_set('max_execution_time', 0);

class IptvScannerOptimized
{
    private $cities = [
        1 => ['name' => '浙江电信', 'stream' => 'udp/233.50.201.100:5140'],
        2 => ['name' => '江苏电信', 'stream' => 'udp/239.49.8.19:9614'],
        3 => ['name' => '湖北电信', 'stream' => 'rtp/239.69.1.40:9880'],
        4 => ['name' => '河南电信', 'stream' => 'rtp/239.16.20.21:10210'],
        5 => ['name' => '河北联通', 'stream' => 'rtp/239.253.92.154:6011'],
        6 => ['name' => '广东电信', 'stream' => 'udp/239.77.1.152:5146'],
        7 => ['name' => '北京联通', 'stream' => 'rtp/239.3.1.241:8000'],
        8 => ['name' => '湖南电信', 'stream' => 'udp/239.76.246.151:1234'],
        9 => ['name' => '辽宁联通', 'stream' => 'rtp/232.0.0.126:1234'],
        10 => ['name' => '四川电信', 'stream' => 'udp/239.93.0.169:5140'],
        11 => ['name' => '山东电信', 'stream' => 'udp/239.21.1.87:5002'],
        12 => ['name' => '陕西电信', 'stream' => 'rtp/239.111.205.35:5140'],
        13 => ['name' => '广西电信', 'stream' => 'udp/239.81.0.107:4056'],
        14 => ['name' => '贵州电信', 'stream' => 'rtp/238.255.2.1:5999'],
        15 => ['name' => '山西联通', 'stream' => 'rtp/226.0.2.152:9128'],
        16 => ['name' => '上海电信', 'stream' => 'udp/239.45.3.146:5140'],
        17 => ['name' => '福建电信', 'stream' => 'rtp/239.61.2.132:8708'],
        18 => ['name' => '江西电信', 'stream' => 'udp/239.252.220.63:5140'],
        19 => ['name' => '安徽电信', 'stream' => 'rtp/238.1.79.27:4328'],
        20 => ['name' => '天津联通', 'stream' => 'udp/225.1.1.111:5002'],
        21 => ['name' => '宁夏电信', 'stream' => 'rtp/239.121.4.94:8538'],
        22 => ['name' => '重庆电信', 'stream' => 'rtp/235.254.196.249:1268'],
        23 => ['name' => '河北电信', 'stream' => 'rtp/239.254.200.174:6000'],
        24 => ['name' => '河南联通', 'stream' => 'rtp/225.1.4.98:1127'],
        25 => ['name' => '海南电信', 'stream' => 'rtp/239.253.64.253:5140'],
        26 => ['name' => '黑龙江联通', 'stream' => 'rtp/229.58.190.150:5000'],
        27 => ['name' => '甘肃电信', 'stream' => 'udp/239.255.30.249:8231'],
        28 => ['name' => '新疆电信', 'stream' => 'udp/238.125.3.174:5140'],
        29 => ['name' => '内蒙古电信', 'stream' => 'rtp/239.29.0.2:5000'],
        30 => ['name' => '北京电信', 'stream' => 'rtp/225.1.8.21:8002'],
        31 => ['name' => '湖北联通', 'stream' => 'rtp/228.0.0.60:6108'],
        32 => ['name' => '吉林电信', 'stream' => 'rtp/239.37.0.231:5540'],
        33 => ['name' => '云南电信', 'stream' => 'rtp/239.200.200.145:8840'],
        34 => ['name' => '山东联通', 'stream' => 'rtp/239.253.254.78:8000'],
        35 => ['name' => '重庆联通', 'stream' => 'udp/225.0.4.187:7980'],
    ];

    private $stage = 'all';
    private $cityChoice = 0;
    private $citiesWithSpeed = [];
    private $parallelCities = false;
    private $maxCityWorkers = 4;
    
    private $speedTestConcurrency = 20;
    private $speedTestDuration = 3;
    private $connectTimeout = 2;

    public function __construct($stage = 'all', $cityChoice = 0, $parallel = false)
    {
        $this->stage = $stage;
        $this->cityChoice = (int)$cityChoice;
        $this->parallelCities = $parallel && function_exists('pcntl_fork');
        
        foreach (['ip', 'template', 'txt', 'speedlog'] as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
        }
    }

    public function run(): void
    {
        $citiesToProcess = $this->cityChoice === 0 
            ? array_keys($this->cities) 
            : [$this->cityChoice];
        
        if ($this->parallelCities && count($citiesToProcess) > 1) {
            $this->runParallelCities($citiesToProcess);
        } else {
            $this->runSequentialCities($citiesToProcess);
        }
        
        if ($this->stage === 'all' && $this->cityChoice === 0) {
            $this->mergeAll();
        }
        
        echo "\n全部完成!\n";
    }

    private function runParallelCities(array $citiesToProcess): void
    {
        echo "[并行模式] 启动 {$this->maxCityWorkers} 个进程处理 " . count($citiesToProcess) . " 个城市\n";
        
        $chunks = array_chunk($citiesToProcess, ceil(count($citiesToProcess) / $this->maxCityWorkers));
        $children = [];
        
        foreach ($chunks as $index => $cityChunk) {
            $pid = pcntl_fork();
            
            if ($pid === -1) {
                echo "无法 fork 进程，回退到串行模式\n";
                $this->runSequentialCities($cityChunk);
            } elseif ($pid === 0) {
                $cityNames = array_map(function($n) { return $this->cities[$n]['name']; }, $cityChunk);
                echo "[子进程 " . getmypid() . "] 处理: " . implode(', ', $cityNames) . "\n";
                foreach ($cityChunk as $cityNum) {
                    $this->processCity($cityNum);
                }
                exit(0);
            } else {
                $children[] = $pid;
            }
        }
        
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        echo "[并行模式] 所有子进程完成\n";
        
        foreach ($citiesToProcess as $cityNum) {
            $cityName = $this->cities[$cityNum]['name'];
            if (file_exists("txt/{$cityName}.txt")) {
                $this->citiesWithSpeed[] = $cityName;
            }
        }
    }

    private function runSequentialCities(array $citiesToProcess): void
    {
        foreach ($citiesToProcess as $cityNum) {
            $this->processCity($cityNum);
        }
    }

    private function processCity(int $cityNum): void
    {
        if (!isset($this->cities[$cityNum])) return;
        
        $cityInfo = $this->cities[$cityNum];
        $cityName = $cityInfo['name'];
        
        echo "\n======== 处理: {$cityName} ========\n";
        
        if (in_array($this->stage, ['all', 'scan'])) {
            $this->scanCity($cityName);
        }
        
        if (in_array($this->stage, ['all', 'test'])) {
            $hasSpeed = $this->testCity($cityName, $cityInfo['stream']);
            if ($hasSpeed) {
                $this->citiesWithSpeed[] = $cityName;
            }
        }
    }

    private function scanCity(string $cityName): void
    {
        $configFile = "ip/{$cityName}_config.txt";
        
        if (!file_exists($configFile)) {
            echo "[扫描] 跳过 {$cityName}（无配置）\n";
            return;
        }
        
        echo "[扫描] {$cityName} 开始...\n";
        
        $configs = $this->readConfig($configFile);
        $allIpPorts = [];
        
        foreach ($configs as $config) {
            list($ip, $port, $option, $urlEnd) = $config;
            $results = $this->scanIpPort($ip, $port, $option, $urlEnd);
            $allIpPorts = array_merge($allIpPorts, $results);
        }
        
        if (!empty($allIpPorts)) {
            $allIpPorts = array_unique($allIpPorts);
            sort($allIpPorts);
            file_put_contents("ip/{$cityName}_ip.txt", implode("\n", $allIpPorts));
            echo "[扫描] {$cityName} 发现 " . count($allIpPorts) . " 个 IP\n";
        }
    }

    private function testCity(string $cityName, string $stream): bool
    {
        $ipFile = "ip/{$cityName}_ip.txt";
        if (!file_exists($ipFile)) {
            echo "[测速] {$cityName} 跳过（无IP文件）\n";
            return false;
        }
        
        $ipList = array_unique(array_filter(file($ipFile, FILE_IGNORE_NEW_LINES)));
        if (empty($ipList)) {
            echo "[测速] {$cityName} 跳过（IP列表空）\n";
            return false;
        }
        
        echo "[测速] {$cityName} 共 " . count($ipList) . " 个 IP\n";
        
        $goodIps = $this->batchConnectTest($ipList);
        if (empty($goodIps)) {
            echo "[测速] {$cityName} 无连通IP\n";
            return false;
        }
        
        echo "[测速] {$cityName} 连通 " . count($goodIps) . " 个，开始并行测速...\n";
        
        $speedResults = $this->batchSpeedTest($goodIps, $stream);
        
        if (empty($speedResults)) {
            echo "[测速] {$cityName} 无测速成功IP\n";
            return false;
        }
        
        arsort($speedResults);
        $top3 = array_slice($speedResults, 0, 3, true);
        
        echo "[测速] {$cityName} 最快3个:\n";
        $idx = 1;
        foreach ($top3 as $ip => $speed) {
            echo "  {$idx}. {$ip} (" . round($speed, 2) . " MB/s)\n";
            $idx++;
        }
        
        $this->generatePlaylist($cityName, array_keys($top3), $stream);
        return true;
    }

    private function batchConnectTest(array $ipList): array
    {
        $goodIps = [];
        $batches = array_chunk($ipList, $this->speedTestConcurrency);
        
        foreach ($batches as $batch) {
            $mh = curl_multi_init();
            $handles = [];
            $ipMap = [];
            
            foreach ($batch as $ipPort) {
                list($ip, $port) = explode(':', $ipPort);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "http://{$ip}:{$port}/",
                    CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                    CURLOPT_TIMEOUT => $this->connectTimeout,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => true,
                ]);
                curl_multi_add_handle($mh, $ch);
                $chId = (int)$ch;
                $handles[$chId] = $ch;
                $ipMap[$chId] = $ipPort;
            }
            
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.1);
            } while ($running > 0);
            
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $chId = (int)$ch;
                $ipPort = $ipMap[$chId];
                
                if ($info['result'] === CURLE_OK) {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($httpCode > 0) {
                        $goodIps[] = $ipPort;
                    }
                }
                
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            
            curl_multi_close($mh);
        }
        
        return $goodIps;
    }

    private function batchSpeedTest(array $ipList, string $stream): array
    {
        $results = [];
        $batches = array_chunk($ipList, $this->speedTestConcurrency);
        $total = count($ipList);
        $processed = 0;
        
        foreach ($batches as $batch) {
            $mh = curl_multi_init();
            $handles = [];
            $ipMap = [];
            $startTimes = [];
            $downloaded = [];
            
            foreach ($batch as $ipPort) {
                $url = "http://{$ipPort}/{$stream}";
                $ch = curl_init();
                
                $chId = (int)$ch;
                $downloaded[$chId] = 0;
                $startTimes[$chId] = microtime(true);
                $ipMap[$chId] = $ipPort;
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => $this->speedTestDuration + 2,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$downloaded, $startTimes) {
                        $chId = (int)$ch;
                        $downloaded[$chId] += strlen($data);
                        if ((microtime(true) - $startTimes[$chId]) >= $this->speedTestDuration) {
                            return 0;
                        }
                        return strlen($data);
                    },
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 2,
                ]);
                
                curl_multi_add_handle($mh, $ch);
                $handles[$chId] = $ch;
            }
            
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.1);
            } while ($running > 0);
            
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $chId = (int)$ch;
                $ipPort = $ipMap[$chId];
                
                if ($info['result'] === CURLE_OK || $info['result'] === CURLE_WRITE_ERROR) {
                    $duration = microtime(true) - $startTimes[$chId];
                    $bytes = $downloaded[$chId];
                    
                    if ($duration > 0 && $bytes > 10000) {
                        $speed = ($bytes / 1024 / 1024) / $duration;
                        $results[$ipPort] = $speed;
                    }
                }
                
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                $processed++;
            }
            
            curl_multi_close($mh);
            echo "[进度] 测速 {$processed}/{$total}\r";
        }
        
        echo "\n";
        return $results;
    }

    private function readConfig(string $configFile): array
    {
        $ipConfigs = [];
        if (!file_exists($configFile)) return [];
        
        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, ',') !== false && strpos($line, '#') !== 0) {
                $parts = array_map('trim', explode(',', $line));
                if (count($parts) < 2) continue;
                
                list($ipPart, $port) = explode(':', $parts[0]);
                $ipSegments = explode('.', $ipPart);
                if (count($ipSegments) !== 4) continue;
                
                list($a, $b, $c, $d) = $ipSegments;
                $option = (int)$parts[1];
                $urlEnd = ($option >= 10) ? "/status" : "/stat";
                $ip = ($option % 2 == 0) ? "{$a}.{$b}.{$c}.1" : "{$a}.{$b}.1.1";
                
                $ipConfigs[] = [$ip, $port, $option, $urlEnd];
            }
        }
        return $ipConfigs;
    }

    private function generateIpPorts(string $ip, string $port, int $option): array
    {
        list($a, $b, $c, $d) = explode('.', $ip);
        $result = [];
        
        if ($option == 2 || $option == 12) {
            $cExtent = explode('-', $c);
            $cFirst = count($cExtent) == 2 ? (int)$cExtent[0] : (int)$c;
            $cLast = count($cExtent) == 2 ? (int)$cExtent[1] + 1 : (int)$c + 8;
            
            for ($x = $cFirst; $x < $cLast; $x++) {
                for ($y = 1; $y < 256; $y++) {
                    $result[] = "{$a}.{$b}.{$x}.{$y}:{$port}";
                }
            }
        } elseif ($option == 0 || $option == 10) {
            for ($y = 1; $y < 256; $y++) {
                $result[] = "{$a}.{$b}.{$c}.{$y}:{$port}";
            }
        } else {
            for ($x = 0; $x < 256; $x++) {
                for ($y = 1; $y < 256; $y++) {
                    $result[] = "{$a}.{$b}.{$x}.{$y}:{$port}";
                }
            }
        }
        
        return $result;
    }

    private function scanIpPort(string $ip, string $port, int $option, string $urlEnd): array
    {
        $validIpPorts = [];
        $ipPorts = $this->generateIpPorts($ip, $port, $option);
        $total = count($ipPorts);
        $checked = 0;
        
        $maxWorkers = ($option % 2 == 1) ? 300 : 100;
        $batchSize = 1000;
        $batches = array_chunk($ipPorts, $batchSize);
        
        foreach ($batches as $batch) {
            $batchResults = $this->processBatch($batch, $urlEnd, $maxWorkers);
            $validIpPorts = array_merge($validIpPorts, $batchResults);
            $checked += count($batch);
            echo "[扫描] 进度 {$checked}/{$total}\r";
        }
        
        echo "\n";
        return $validIpPorts;
    }

    private function processBatch(array $ipPorts, string $urlEnd, int $concurrency): array
    {
        $valid = [];
        $mh = curl_multi_init();
        $handles = [];
        $ipMap = [];
        $active = null;
        
        $initialCount = min($concurrency, count($ipPorts));
        for ($i = 0; $i < $initialCount; $i++) {
            $ch = $this->createHandle($ipPorts[$i], $urlEnd);
            curl_multi_add_handle($mh, $ch);
            $chId = (int)$ch;
            $handles[$chId] = $ch;
            $ipMap[$chId] = $ipPorts[$i];
        }
        
        $nextIndex = $initialCount;
        
        do {
            curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 0.1);
            
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $chId = (int)$ch;
                $ipPort = $ipMap[$chId] ?? 'unknown';
                
                if ($info['result'] === CURLE_OK) {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $response = curl_multi_getcontent($ch);
                    
                    if ($httpCode === 200 && $response !== false) {
                        if (strpos($response, 'Multi stream daemon') !== false || 
                            strpos($response, 'udpxy status') !== false) {
                            $valid[] = $ipPort;
                        }
                    }
                }
                
                if ($nextIndex < count($ipPorts)) {
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    
                    $newCh = $this->createHandle($ipPorts[$nextIndex], $urlEnd);
                    curl_multi_add_handle($mh, $newCh);
                    $newChId = (int)$newCh;
                    $handles[$newChId] = $newCh;
                    $ipMap[$newChId] = $ipPorts[$nextIndex];
                    $nextIndex++;
                } else {
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($handles[$chId]);
                }
            }
        } while ($active > 0);
        
        curl_multi_close($mh);
        return $valid;
    }

    private function createHandle(string $ipPort, string $urlEnd)
    {
        $url = "http://{$ipPort}{$urlEnd}";
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; IptvScanner/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        return $ch;
    }

    private function generatePlaylist(string $cityName, array $topIps, string $stream): void
    {
        $templateFile = "template/template_{$cityName}.txt";
        
        if (!file_exists($templateFile)) {
            echo "[生成] 模板不存在: {$templateFile}\n";
            return;
        }
        
        $template = file_get_contents($templateFile);
        $output = [];
        
        foreach ($topIps as $idx => $ip) {
            $groupName = "{$cityName}-组播" . ($idx + 1);
            $output[] = "{$groupName},#genre#";
            $channels = str_replace('ipipip', $ip, $template);
            $output[] = trim($channels);
        }
        
        $finalOutput = array_filter($output, function($line) {
            return strpos($line, '///') === false;
        });
        file_put_contents("txt/{$cityName}.txt", implode("\n", $finalOutput));
        echo "[生成] {$cityName} 播放列表已保存\n";
    }

    private function mergeAll(): void
    {
        echo "\n[合并] 开始合并...\n";
        
        $allContent = [];
        $time = date('m/d H:i');
        $allContent[] = "{$time} 更新,#genre#";
        $allContent[] = "浙江卫视,http://ali-m-l.cztv.com/channels/lantian/channel001/1080p.m3u8";
        
        $mergedCount = 0;
        foreach ($this->citiesWithSpeed as $cityName) {
            $file = "txt/{$cityName}.txt";
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (!empty(trim($content)) && strpos($content, '#genre#') !== false) {
                    $allContent[] = trim($content);
                    $mergedCount++;
                }
            }
        }
        
        if ($mergedCount === 0) {
            echo "[合并] 无数据可合并\n";
            return;
        }
        
        $finalContent = implode("\n", $allContent);
        file_put_contents('zubo_all.txt', $finalContent);
        $this->generateM3u($finalContent);
        
        echo "[合并] 完成: {$mergedCount} 个城市 -> zubo_all.txt/m3u\n";
    }

    private function generateM3u(string $txtContent): void
    {
        $lines = explode("\n", $txtContent);
        $m3u = ["#EXTM3U"];
        $group = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, ',') !== false) {
                list($name, $url) = explode(',', $line, 2);
                
                if ($url === '#genre#') {
                    $group = $name;
                } else {
                    $m3u[] = "#EXTINF:-1 group-title=\"{$group}\",{$name}";
                    $m3u[] = $url;
                }
            }
        }
        
        file_put_contents('zubo_all.m3u', implode("\n", $m3u));
    }
}

$stage = $argv[1] ?? 'all';
$city = $argv[2] ?? 0;
$parallel = ($argv[3] ?? 'parallel') !== 'single';  // 默认并行，传入 single 则串行

if (!in_array($stage, ['scan', 'test', 'all'])) {
    echo "用法: php zubo.php [stage] [city] [mode]\n";
    echo "  stage: scan | test | all\n";
    echo "  city: 0-35 (0=全部)\n";
    echo "  mode: parallel(默认并行) | single(串行)\n";
    exit(1);
}

$scanner = new IptvScannerOptimized($stage, $city, $parallel);
$scanner->run();
