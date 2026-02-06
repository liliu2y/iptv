<?php
/**
 * IPTV 组播源扫描测速工具 - PHP 整合版
 * 兼容 PHP 7.4+
 * 
 * 使用方式:
 * php zubo.php [stage] [city_number|operator]
 * 
 * stage: 
 *   scan    - 只执行扫描阶段
 *   test    - 只执行测速阶段  
 *   all     - 执行全部阶段（默认）
 * 
 * city_number: 1-35, 0（全部）, 或运营商名称（联通/电信/移动）
 */

class IptvScanner
{
    // 城市配置（从 shell 脚本移植）
    private $cities = [
        1 => ['name' => '浙江电信', 'stream' => 'udp/233.50.201.100:5140', 'operator' => '电信'],
        2 => ['name' => '江苏电信', 'stream' => 'udp/239.49.8.19:9614', 'operator' => '电信'],
        3 => ['name' => '湖北电信', 'stream' => 'rtp/239.69.1.40:9880', 'operator' => '电信'],
        4 => ['name' => '河南电信', 'stream' => 'rtp/239.16.20.21:10210', 'operator' => '电信'],
        5 => ['name' => '河北联通', 'stream' => 'rtp/239.253.92.154:6011', 'operator' => '联通'],
        6 => ['name' => '广东电信', 'stream' => 'udp/239.77.1.152:5146', 'operator' => '电信'],
        7 => ['name' => '北京联通', 'stream' => 'rtp/239.3.1.241:8000', 'operator' => '联通'],
        8 => ['name' => '湖南电信', 'stream' => 'udp/239.76.246.151:1234', 'operator' => '电信'],
        9 => ['name' => '辽宁联通', 'stream' => 'rtp/232.0.0.126:1234', 'operator' => '联通'],
        10 => ['name' => '四川电信', 'stream' => 'udp/239.93.0.169:5140', 'operator' => '电信'],
        11 => ['name' => '山东电信', 'stream' => 'udp/239.21.1.87:5002', 'operator' => '电信'],
        12 => ['name' => '陕西电信', 'stream' => 'rtp/239.111.205.35:5140', 'operator' => '电信'],
        13 => ['name' => '广西电信', 'stream' => 'udp/239.81.0.107:4056', 'operator' => '电信'],
        14 => ['name' => '贵州电信', 'stream' => 'rtp/238.255.2.1:5999', 'operator' => '电信'],
        15 => ['name' => '山西联通', 'stream' => 'rtp/226.0.2.152:9128', 'operator' => '联通'],
        16 => ['name' => '上海电信', 'stream' => 'udp/239.45.3.146:5140', 'operator' => '电信'],
        17 => ['name' => '福建电信', 'stream' => 'rtp/239.61.2.132:8708', 'operator' => '电信'],
        18 => ['name' => '江西电信', 'stream' => 'udp/239.252.220.63:5140', 'operator' => '电信'],
        19 => ['name' => '安徽电信', 'stream' => 'rtp/238.1.79.27:4328', 'operator' => '电信'],
        20 => ['name' => '天津联通', 'stream' => 'udp/225.1.1.111:5002', 'operator' => '联通'],
        21 => ['name' => '宁夏电信', 'stream' => 'rtp/239.121.4.94:8538', 'operator' => '电信'],
        22 => ['name' => '重庆电信', 'stream' => 'rtp/235.254.196.249:1268', 'operator' => '电信'],
        23 => ['name' => '河北电信', 'stream' => 'rtp/239.254.200.174:6000', 'operator' => '电信'],
        24 => ['name' => '河南联通', 'stream' => 'rtp/225.1.4.98:1127', 'operator' => '联通'],
        25 => ['name' => '海南电信', 'stream' => 'rtp/239.253.64.253:5140', 'operator' => '电信'],
        26 => ['name' => '黑龙江联通', 'stream' => 'rtp/229.58.190.150:5000', 'operator' => '联通'],
        27 => ['name' => '甘肃电信', 'stream' => 'udp/239.255.30.249:8231', 'operator' => '电信'],
        28 => ['name' => '新疆电信', 'stream' => 'udp/238.125.3.174:5140', 'operator' => '电信'],
        29 => ['name' => '内蒙古电信', 'stream' => 'rtp/239.29.0.2:5000', 'operator' => '电信'],
        30 => ['name' => '北京电信', 'stream' => 'rtp/225.1.8.21:8002', 'operator' => '电信'],
        31 => ['name' => '湖北联通', 'stream' => 'rtp/228.0.0.60:6108', 'operator' => '联通'],
        32 => ['name' => '吉林电信', 'stream' => 'rtp/239.37.0.231:5540', 'operator' => '电信'],
        33 => ['name' => '云南电信', 'stream' => 'rtp/239.200.200.145:8840', 'operator' => '电信'],
        34 => ['name' => '山东联通', 'stream' => 'rtp/239.253.254.78:8000', 'operator' => '联通'],
        35 => ['name' => '重庆联通', 'stream' => 'udp/225.0.4.187:7980', 'operator' => '联通'],
    ];

    private $checked = [0];
    private $validIpPorts = [];
    private $totalToCheck = 0;
    private $stage = 'all';
    private $cityChoice = '联通';
    private $selectedCities = [];
    private $autoRun = false;  // 是否自动运行

    public function __construct($stage = 'all', $cityChoice = '联通', $autoRun = false)
    {
        $this->stage = $stage;
        $this->cityChoice = $cityChoice;
        $this->autoRun = $autoRun;
        
        // 确保目录存在
        foreach (['ip', 'template', 'txt', 'speedlog'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        $this->parseCityChoice();
    }

    /**
     * 解析城市选择
     */
    private function parseCityChoice(): void
    {
        if (is_numeric($this->cityChoice)) {
            $num = (int)$this->cityChoice;
            if ($num === 0) {
                $this->selectedCities = array_keys($this->cities);
            } elseif (isset($this->cities[$num])) {
                $this->selectedCities = [$num];
            }
        } else {
            $operator = $this->cityChoice;
            $this->selectedCities = [];
            foreach ($this->cities as $num => $info) {
                if ($info['operator'] === $operator) {
                    $this->selectedCities[] = $num;
                }
            }
            
            if (empty($this->selectedCities)) {
                echo "[提示] 未找到 '{$operator}'，使用默认：联通\n";
                $this->cityChoice = '联通';
                foreach ($this->cities as $num => $info) {
                    if ($info['operator'] === '联通') {
                        $this->selectedCities[] = $num;
                    }
                }
            }
        }
    }

    /**
     * 显示菜单并获取选择（带5秒超时）
     */
    public function showMenu(): void
    {
        echo "======== IPTV 组播源扫描测速工具 ========\n";
        echo "执行阶段: {$this->stage}\n";
        
        // 按运营商分组显示
        $byOperator = [];
        foreach ($this->cities as $num => $city) {
            $byOperator[$city['operator']][] = ['num' => $num, 'name' => $city['name']];
        }
        
        echo "\n【默认已选择: 联通】\n";
        foreach ($byOperator as $op => $cities) {
            echo "  {$op}: ";
            $names = array_map(function($c) {
                return in_array($c['num'], $this->selectedCities) ? "**{$c['name']}**" : $c['name'];
            }, $cities);
            echo implode(', ', array_slice($names, 0, 3)) . " 等" . count($cities) . "个\n";
        }
        
        echo "\n可选操作:\n";
        echo "  1. 直接回车 - 使用默认（联通）\n";
        echo "  2. 输入编号 - 选择单个城市（1-35）\n";
        echo "  3. 输入运营商 - 选择全部电信/联通/移动\n";
        echo "  4. 输入 0 - 选择全部城市\n";
        echo "========================================\n";
        
        // 只有在终端模式且未指定参数时才提示输入
        if (!$this->autoRun && php_sapi_name() === 'cli' && posix_isatty(STDIN)) {
            echo "\n5秒后自动使用默认值（联通）运行...\n";
            echo "请输入选择 (或直接回车): ";
            
            // 设置非阻塞读取，5秒超时
            $input = $this->readInputWithTimeout(5);
            
            if ($input !== null && trim($input) !== '') {
                $input = trim($input);
                if (is_numeric($input)) {
                    $this->cityChoice = (int)$input;
                } else {
                    $this->cityChoice = $input;
                }
                $this->selectedCities = [];
                $this->parseCityChoice();
                echo "\n已手动选择: {$this->cityChoice}\n";
            } else {
                echo "\n超时，使用默认值: 联通\n";
            }
        } else {
            echo "\n自动模式，使用默认值: 联通\n";
        }
        
        $count = count($this->selectedCities);
        echo "\n最终选择: {$count} 个城市 ({$this->cityChoice})\n";
        if ($count <= 5) {
            foreach ($this->selectedCities as $num) {
                echo "  - {$this->cities[$num]['name']}\n";
            }
        } else {
            echo "  - " . implode(', ', array_map(function($n) {
                return $this->cities[$n]['name'];
            }, array_slice($this->selectedCities, 0, 5))) . " 等共{$count}个\n";
        }
        echo "\n";
    }

    /**
     * 带超时的输入读取
     */
    private function readInputWithTimeout(int $timeout): ?string
    {
        // 使用 stream_select 实现超时读取
        $read = [STDIN];
        $write = null;
        $except = null;
        
        $result = stream_select($read, $write, $except, $timeout);
        
        if ($result === false || $result === 0) {
            // 超时或错误
            return null;
        }
        
        // 有输入，读取一行
        $input = fgets(STDIN);
        return $input !== false ? $input : null;
    }

    /**
     * 主执行流程
     */
    public function run(): void
    {
        $this->showMenu();
        
        if (empty($this->selectedCities)) {
            echo "错误: 未选择任何城市\n";
            return;
        }
        
        foreach ($this->selectedCities as $cityNum) {
            $cityInfo = $this->cities[$cityNum];
            $cityName = $cityInfo['name'];
            
            echo "\n======== 处理: {$cityName} ========\n";
            
            if (in_array($this->stage, ['all', 'scan'])) {
                $this->scanCity($cityName);
            }
            
            if (in_array($this->stage, ['all', 'test'])) {
                $this->testCity($cityName, $cityInfo['stream']);
            }
        }
        
        if ($this->stage === 'all') {
            $this->mergeAll();
        }
        
        echo "\n全部完成!\n";
    }

    // ==================== 扫描阶段 ====================

    private function scanCity(string $cityName): void
    {
        $configFile = "ip/{$cityName}_config.txt";
        
        if (!file_exists($configFile)) {
            echo "[扫描] 配置不存在: {$configFile}, 跳过\n";
            return;
        }
        
        echo "[扫描] 开始扫描 {$cityName}...\n";
        
        $configs = $this->readConfig($configFile);
        if (empty($configs)) {
            echo "[扫描] 配置为空\n";
            return;
        }
        
        $allIpPorts = [];
        foreach ($configs as $config) {
            list($ip, $port, $option, $urlEnd) = $config;
            echo "[扫描] 网段: http://{$ip}:{$port}{$urlEnd}\n";
            $results = $this->scanIpPort($ip, $port, $option, $urlEnd);
            $allIpPorts = array_merge($allIpPorts, $results);
        }
        
        if (!empty($allIpPorts)) {
            $allIpPorts = array_unique($allIpPorts);
            sort($allIpPorts);
            
            $outputFile = "ip/{$cityName}_ip.txt";
            file_put_contents($outputFile, implode("\n", $allIpPorts));
            echo "[扫描] 发现 " . count($allIpPorts) . " 个 IP → {$outputFile}\n";
        } else {
            echo "[扫描] 未发现可用 IP\n";
        }
    }

    private function readConfig(string $configFile): array
    {
        $ipConfigs = [];
        if (!file_exists($configFile)) return [];
        
        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return [];
        
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
        $this->validIpPorts = [];
        $ipPorts = $this->generateIpPorts($ip, $port, $option);
        $this->totalToCheck = count($ipPorts);
        $this->checked[0] = 0;
        
        $maxWorkers = ($option % 2 == 1) ? 300 : 100;
        echo "[扫描] 共 {$this->totalToCheck} 个地址，并发 {$maxWorkers}\n";
        
        $batchSize = 1000;
        $batches = array_chunk($ipPorts, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $this->processBatch($batch, $urlEnd, $maxWorkers, $option);
        }
        
        return $this->validIpPorts;
    }

    private function processBatch(array $ipPorts, string $urlEnd, int $concurrency, int $option): void
    {
        $mh = curl_multi_init();
        $handles = [];
        $active = null;
        
        $initialCount = min($concurrency, count($ipPorts));
        for ($i = 0; $i < $initialCount; $i++) {
            $ch = $this->createHandle($ipPorts[$i], $urlEnd);
            curl_multi_add_handle($mh, $ch);
            $handles[(int)$ch] = ['handle' => $ch, 'ip' => $ipPorts[$i]];
        }
        
        $nextIndex = $initialCount;
        
        do {
            curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 0.1);
            
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $handleId = (int)$ch;
                $ipPort = $handles[$handleId]['ip'] ?? 'unknown';
                
                if ($info['result'] === CURLE_OK) {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $response = curl_multi_getcontent($ch);
                    
                    if ($httpCode === 200 && $response !== false) {
                        if (strpos($response, 'Multi stream daemon') !== false || 
                            strpos($response, 'udpxy status') !== false) {
                            $this->validIpPorts[] = $ipPort;
                        }
                    }
                }
                
                $this->checked[0]++;
                
                if ($nextIndex < count($ipPorts)) {
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    
                    $newCh = $this->createHandle($ipPorts[$nextIndex], $urlEnd);
                    curl_multi_add_handle($mh, $newCh);
                    $handles[(int)$newCh] = ['handle' => $newCh, 'ip' => $ipPorts[$nextIndex]];
                    $nextIndex++;
                } else {
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($handles[$handleId]);
                }
            }
        } while ($active > 0);
        
        curl_multi_close($mh);
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

    // ==================== 测速阶段 ====================

    private function testCity(string $cityName, string $stream): void
    {
        $ipFile = "ip/{$cityName}_ip.txt";
        
        if (!file_exists($ipFile)) {
            echo "[测速] IP文件不存在: {$ipFile}, 跳过\n";
            return;
        }
        
        echo "[测速] 开始测试 {$cityName}...\n";
        
        $ipList = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ipList = array_unique(array_filter($ipList));
        
        if (empty($ipList)) {
            echo "[测速] IP列表为空\n";
            return;
        }
        
        echo "[测速] 共 " . count($ipList) . " 个IP，测试连通性...\n";
        
        $goodIps = [];
        foreach ($ipList as $ip) {
            if ($this->testConnect($ip)) {
                $goodIps[] = $ip;
            }
        }
        
        $goodCount = count($goodIps);
        echo "[测速] 连通成功 {$goodCount} 个，开始测速...\n";
        
        if ($goodCount === 0) {
            echo "[测速] 无可用IP\n";
            return;
        }
        
        $speedResults = [];
        $i = 0;
        foreach ($goodIps as $ip) {
            $i++;
            $speed = $this->testSpeed($ip, $stream);
            $speedStr = $speed > 0 ? round($speed, 2) . ' MB/s' : '失败';
            echo "[测速] {$i}/{$goodCount}: {$ip} => {$speedStr}\n";
            
            if ($speed > 0) {
                $speedResults[$ip] = $speed;
            }
        }
        
        arsort($speedResults);
        $top3 = array_slice(array_keys($speedResults), 0, 3);
        
        echo "[测速] 最快3个:\n";
        foreach ($top3 as $idx => $ip) {
            echo "  " . ($idx + 1) . ". {$ip} (" . round($speedResults[$ip], 2) . " MB/s)\n";
        }
        
        $this->generatePlaylist($cityName, $top3, $stream);
    }

    private function testConnect(string $ipPort): bool
    {
        list($ip, $port) = explode(':', $ipPort);
        $fp = @fsockopen($ip, $port, $errno, $errstr, 1);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    private function testSpeed(string $ipPort, string $stream): float
    {
        $url = "http://{$ipPort}/{$stream}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function($ch, $data) { return strlen($data); },
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        $startTime = microtime(true);
        curl_exec($ch);
        $totalTime = microtime(true) - $startTime;
        
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        if ($info['http_code'] == 200 && $totalTime > 0 && $info['size_download'] > 0) {
            return ($info['size_download'] / 1024 / 1024) / $totalTime;
        }
        
        return 0;
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
            $output[] = trim(str_replace('ipipip', $ip, $template));
        }
        
        $finalOutput = array_filter($output, function($line) {
            return strpos($line, '///') === false;
        });
        
        $outputFile = "txt/{$cityName}.txt";
        file_put_contents($outputFile, implode("\n", $finalOutput));
        echo "[生成] 已保存: {$outputFile}\n";
    }

    // ==================== 合并阶段 ====================

    private function mergeAll(): void
    {
        echo "\n[合并] 开始合并...\n";
        
        $allContent = [];
        $time = date('m/d H:i');
        $allContent[] = "{$time} 更新,#genre#";
        $allContent[] = "浙江卫视,http://ali-m-l.cztv.com/channels/lantian/channel001/1080p.m3u8";
        
        foreach (['电信', '联通', '移动'] as $op) {
            $files = glob("txt/*{$op}.txt");
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $allContent[] = trim(file_get_contents($file));
                }
            }
        }
        
        $finalContent = implode("\n", array_filter($allContent));
        file_put_contents('zubo_all.txt', $finalContent);
        
        $this->generateM3u($finalContent);
        
        echo "[合并] 已生成 zubo_all.txt / zubo_all.m3u\n";
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

// ==================== 命令行入口 ====================

$stage = $argv[1] ?? 'all';
$city = $argv[2] ?? '联通';

// 检查是否有 -y 或 --auto 参数表示自动运行
$autoRun = in_array('-y', $argv) || in_array('--auto', $argv);

if (!in_array($stage, ['scan', 'test', 'all'])) {
    echo "用法: php zubo.php [stage] [city|operator] [-y|--auto]\n";
    echo "  stage:  scan(扫描) | test(测速) | all(全部,默认)\n";
    echo "  city:   0-35 | 联通(默认) | 电信 | 移动\n";
    echo "  -y:     自动运行，不等待输入\n";
    exit(1);
}

$scanner = new IptvScanner($stage, $city, $autoRun);
$scanner->run();
