<?php
/**
 * IPTV 组播源扫描测速工具 - PHP 整合版
 * 兼容 PHP 7.4+
 * 
 * 使用方式:
 * php zubo.php [stage] [city_number]
 * 
 * stage: 
 *   scan    - 只执行扫描阶段
 *   test    - 只执行测速阶段  
 *   all     - 执行全部阶段（默认）
 * 
 * city_number: 1-35 或 0（全部，默认）
 */

// 1. 不设置运行时间限制
set_time_limit(0);
ini_set('max_execution_time', 0);

class IptvScanner
{
    // 城市配置（从 shell 脚本移植）
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

    private $checked = [0];
    private $validIpPorts = [];
    private $totalToCheck = 0;
    private $stage = 'all';
    private $cityChoice = 0;
    // 记录有测速IP的省份
    private $citiesWithSpeed = [];

    public function __construct($stage = 'all', $cityChoice = 0)
    {
        $this->stage = $stage;
        $this->cityChoice = (int)$cityChoice;
        
        // 确保目录存在
        foreach (['ip', 'template', 'txt', 'speedlog'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * 显示菜单并获取选择（带5秒倒计时）
     */
    public function showMenu(): void
    {
        echo "======== IPTV 组播源扫描测速工具 ========\n";
        echo "执行阶段: {$this->stage}\n";
        echo "选择城市:\n";
        
        foreach ($this->cities as $num => $city) {
            echo sprintf("%2d. %s\n", $num, $city['name']);
        }
        echo " 0. 全部城市\n";
        echo "========================================\n";
        
        // 2. 等待5秒后自动运行默认
        if ($this->cityChoice === 0 && php_sapi_name() === 'cli' && posix_isatty(STDIN)) {
            echo "\n请输入编号 (0-35, 默认0): ";
            
            // 倒计时5秒
            $selected = false;
            for ($i = 5; $i > 0; $i--) {
                echo "\r请输入编号 (0-35, 默认0) [{$i}秒后自动运行默认]: ";
                
                // 使用 stream_select 实现非阻塞输入
                $read = [STDIN];
                $write = null;
                $except = null;
                $timeout = 1; // 1秒超时
                
                if (stream_select($read, $write, $except, $timeout) > 0) {
                    $input = trim(fgets(STDIN));
                    if ($input !== '') {
                        $this->cityChoice = is_numeric($input) ? (int)$input : 0;
                        $selected = true;
                        break;
                    }
                }
            }
            
            if (!$selected) {
                echo "\r请输入编号 (0-35, 默认0) [自动运行默认]      \n";
            }
        }
        
        echo "选择: " . ($this->cityChoice === 0 ? '全部' : $this->cities[$this->cityChoice]['name']) . "\n\n";
    }

    /**
     * 主执行流程
     */
    public function run(): void
    {
        $this->showMenu();
        
        $citiesToProcess = $this->cityChoice === 0 ? array_keys($this->cities) : [$this->cityChoice];
        
        foreach ($citiesToProcess as $cityNum) {
            if (!isset($this->cities[$cityNum])) continue;
            
            $cityInfo = $this->cities[$cityNum];
            $cityName = $cityInfo['name'];
            
            echo "\n======== 处理: {$cityName} ========\n";
            
            // 阶段1: 扫描（如果配置存在且需要扫描）
            if (in_array($this->stage, ['all', 'scan'])) {
                $this->scanCity($cityName);
            }
            
            // 阶段2: 测速
            if (in_array($this->stage, ['all', 'test'])) {
                $hasSpeed = $this->testCity($cityName, $cityInfo['stream']);
                if ($hasSpeed) {
                    $this->citiesWithSpeed[] = $cityName;
                }
            }
        }
        
        // 阶段3: 合并（仅全部执行时）
        if ($this->stage === 'all' && $this->cityChoice === 0) {
            $this->mergeAll();
        }
        
        echo "\n全部完成!\n";
    }

    // ==================== 扫描阶段（原 PHP 功能） ====================

    /**
     * 扫描指定城市
     */
    private function scanCity(string $cityName): void
    {
        $configFile = "ip/{$cityName}_config.txt";
        
        if (!file_exists($configFile)) {
            echo "[扫描] 配置文件不存在: {$configFile}, 跳过扫描阶段\n";
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
            echo "[扫描] 发现 " . count($allIpPorts) . " 个可用 IP，保存到 {$outputFile}\n";
        } else {
            echo "[扫描] 未发现可用 IP\n";
        }
    }

    /**
     * 读取配置文件
     */
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

    /**
     * 生成IP端口列表
     */
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

    /**
     * 多线程扫描IP端口
     */
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

    /**
     * 处理一批IP（并行cURL）
     */
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

    // ==================== 测速阶段（原 Shell 功能） ====================

    /**
     * 测速指定城市
     * @return bool 是否有测速成功的IP
     */
    private function testCity(string $cityName, string $stream): bool
    {
        $ipFile = "ip/{$cityName}_ip.txt";
        $goodIpFile = "ip/good_{$cityName}_ip.txt";
        
        if (!file_exists($ipFile)) {
            echo "[测速] IP 文件不存在: {$ipFile}, 跳过\n";
            return false;
        }
        
        echo "[测速] 开始测试 {$cityName}...\n";
        
        // 读取并去重
        $ipList = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ipList = array_unique(array_filter($ipList));
        
        if (empty($ipList)) {
            echo "[测速] IP 列表为空\n";
            return false;
        }
        
        echo "[测速] 共 " . count($ipList) . " 个 IP，开始连通性测试...\n";
        
        // 连通性测试
        $goodIps = [];
        foreach ($ipList as $ip) {
            if ($this->testConnect($ip)) {
                $goodIps[] = $ip;
            }
        }
        
        $goodCount = count($goodIps);
        echo "[测速] 连通成功 {$goodCount} 个，开始速度测试...\n";
        
        if ($goodCount === 0) {
            echo "[测速] 无可用 IP\n";
            return false;
        }
        
        // 速度测试
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
        
        // 如果没有测速成功的IP，返回false
        if (empty($speedResults)) {
            echo "[测速] 无测速成功的 IP\n";
            return false;
        }
        
        // 排序取前3
        arsort($speedResults);
        $top3 = array_slice(array_keys($speedResults), 0, 3);
        
        echo "[测速] 最快 3 个 IP:\n";
        foreach ($top3 as $idx => $ip) {
            $speed = $speedResults[$ip];
            echo "  " . ($idx + 1) . ". {$ip} (" . round($speed, 2) . " MB/s)\n";
        }
        
        // 生成播放列表
        $this->generatePlaylist($cityName, $top3, $stream);
        
        return true; // 有测速成功的IP
    }

    /**
     * 测试 TCP 连通性（模拟 nc -v -z）
     */
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

    /**
     * 测试下载速度（模拟 curl 测速）
     */
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
        
        // 计算速度 MB/s
        if ($info['http_code'] == 200 && $totalTime > 0 && $info['size_download'] > 0) {
            return ($info['size_download'] / 1024 / 1024) / $totalTime;
        }
        
        return 0;
    }

    /**
     * 生成播放列表
     */
    private function generatePlaylist(string $cityName, array $topIps, string $stream): void
    {
        $templateFile = "template/template_{$cityName}.txt";
        
        if (!file_exists($templateFile)) {
            echo "[生成] 模板文件不存在: {$templateFile}\n";
            return;
        }
        
        $template = file_get_contents($templateFile);
        $output = [];
        
        foreach ($topIps as $idx => $ip) {
            $groupName = "{$cityName}-组播" . ($idx + 1);
            $output[] = "{$groupName},#genre#";
            
            // 替换模板中的 ipipip
            $channels = str_replace('ipipip', $ip, $template);
            $output[] = trim($channels);
        }
        
        // 过滤掉包含 /// 的行（模拟 grep -vE '/{3}'）
        $finalOutput = [];
        foreach ($output as $line) {
            if (strpos($line, '///') === false) {
                $finalOutput[] = $line;
            }
        }
        
        $outputFile = "txt/{$cityName}.txt";
        file_put_contents($outputFile, implode("\n", $finalOutput));
        echo "[生成] 播放列表已保存: {$outputFile}\n";
    }

    // ==================== 合并阶段 ====================

    /**
     * 合并所有城市的播放列表
     * 3. 只拼接有测速IP的省份
     */
    private function mergeAll(): void
    {
        echo "\n[合并] 开始合并所有城市...\n";
        
        $allContent = [];
        $time = date('m/d H:i');
        $allContent[] = "{$time} 更新,#genre#";
        $allContent[] = "浙江卫视,http://ali-m-l.cztv.com/channels/lantian/channel001/1080p.m3u8";
        
        // 3. 只拼接有测速IP的省份（从已记录的 citiesWithSpeed 中获取）
        $mergedCount = 0;
        
        foreach ($this->citiesWithSpeed as $cityName) {
            $file = "txt/{$cityName}.txt";
            if (file_exists($file)) {
                $content = file_get_contents($file);
                // 检查内容是否非空且包含有效的频道信息
                if (!empty(trim($content)) && strpos($content, '#genre#') !== false) {
                    $allContent[] = trim($content);
                    $mergedCount++;
                    echo "[合并] 已添加: {$cityName}\n";
                } else {
                    echo "[合并] 跳过空内容: {$cityName}\n";
                }
            } else {
                echo "[合并] 文件不存在: {$cityName}\n";
            }
        }
        
        if ($mergedCount === 0) {
            echo "[合并] 警告: 没有可合并的省份数据\n";
            return;
        }
        
        $finalContent = implode("\n", $allContent);
        file_put_contents('zubo_all.txt', $finalContent);
        
        // 同时生成 M3U
        $this->generateM3u($finalContent);
        
        echo "[合并] 已生成 zubo_all.txt 和 zubo_all.m3u (共 {$mergedCount} 个省份)\n";
    }

    /**
     * 生成 M3U 格式
     */
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
$city = $argv[2] ?? 0;

// 验证参数
if (!in_array($stage, ['scan', 'test', 'all'])) {
    echo "用法: php zubo.php [stage] [city_number]\n";
    echo "  stage: scan(扫描) | test(测速) | all(全部)\n";
    echo "  city_number: 0-35 (0=全部)\n";
    exit(1);
}

$scanner = new IptvScanner($stage, $city);
$scanner->run();
