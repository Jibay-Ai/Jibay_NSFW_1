<?php

















declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('memory_limit', '768M');
ini_set('max_execution_time', '60');
set_time_limit(60);

final class JsonResponse
{
    public static function send(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            $json = '{"success":false,"code":"JSON_ENCODING_FAILED","message":"The response could not be encoded."}';
        }

        echo $json;
        exit;
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): never
    {
        $payload = [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        self::send($payload, $status);
    }
}

final class ModelIdentity
{
    public const NAME = 'Jibay_NSFW-1';
    public const NAME_SHA256 = 'd9bcc777c8d51af7f0baaeabdfc9101d90626fa4eb895e4fc0757a1b0a4b054b';
    public const ENGINE_VERSION = 5;
    public const ANALYZER_REVISION = 'white-neutral-guard-v2';

    public static function metadata(): array
    {
        return [
            'model_name' => self::NAME,
            'model_name_sha256' => self::NAME_SHA256,
        ];
    }

    public static function assertDocument(array $document, string $source): void
    {
        $name = isset($document['model_name']) ? trim((string)$document['model_name']) : '';
        $storedHash = isset($document['model_name_sha256'])
            ? strtolower(trim((string)$document['model_name_sha256']))
            : '';
        $computedHash = hash('sha256', $name);

        $valid = $name !== ''
            && hash_equals(self::NAME_SHA256, $computedHash)
            && hash_equals(self::NAME_SHA256, $storedHash)
            && hash_equals(self::NAME, $name);

        if (!$valid) {
            JsonResponse::error(
                'MODEL_CONFIGURATION_NOT_FOUND',
                'The Jibay_NSFW-1 model configuration was not found or failed integrity validation.',
                500,
                ['source' => $source]
            );
        }
    }
}

final class AtomicJsonFile
{
    public static function read(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return null;
            }

            $raw = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function write(string $path, array $data): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($json === false) {
            return false;
        }

        $lockPath = $path . '.lock';
        $lock = @fopen($lockPath, 'c+b');
        if ($lock === false) {
            return false;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return false;
            }

            $temp = $path . '.tmp.' . bin2hex(random_bytes(6));
            $written = @file_put_contents($temp, $json . PHP_EOL, LOCK_EX);
            if ($written === false) {
                @unlink($temp);
                flock($lock, LOCK_UN);
                return false;
            }

            @chmod($temp, 0664);
            $ok = @rename($temp, $path);
            if (!$ok) {
                @unlink($temp);
            }

            flock($lock, LOCK_UN);
            return $ok;
        } catch (Throwable $e) {
            return false;
        } finally {
            fclose($lock);
        }
    }
}

final class AppConfig
{
    private const CONFIG_FILE = __DIR__ . '/config.json';

    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function defaults(): array
    {
        return [
            'version' => ModelIdentity::ENGINE_VERSION,
            'model_name' => ModelIdentity::NAME,
            'model_name_sha256' => ModelIdentity::NAME_SHA256,
            'analyzer_revision' => ModelIdentity::ANALYZER_REVISION,
            'mode' => 1,
            'endpoint' => [
                'source_parameter' => 'url',
                'allow_get_mode_override' => true,
                'allow_debug_output' => true,
            ],
            'storage' => [
                'directory' => 'storage/moderated',
                'public_prefix' => 'storage/moderated',
                'filename_prefix' => 'moderated_',
                'jpeg_quality' => 91,
            ],
            'limits' => [
                'max_file_bytes' => 12582912,
                'max_image_pixels' => 40000000,
                'download_timeout_seconds' => 20,
                'analysis_max_side' => 420,
            ],
            'analysis' => [
                'explicit_threshold' => 0.665,
                'skin_mask_threshold' => 0.515,
                'soft_skin_threshold' => 0.385,
                'minimum_component_ratio' => 0.0010,
                'morphology_iterations' => 1,
                'adaptive_white_balance' => true,
                'topology_grid_size' => 5,
                'consensus_bonus_max' => 0.040,
                'white_pixel_threshold' => 0.58,
                'neutral_pixel_threshold' => 0.70,
                'chromatic_skin_threshold' => 0.28,
                'minimum_skin_ratio_for_explicit' => 0.055,
                'minimum_chromatic_skin_ratio' => 0.032,
                'minimum_positive_votes' => 3,
                'white_garment_guard' => true,
                'tone_profiles' => [
                    ['name' => 'porcelain_neutral', 'cb' => 104.0, 'cr' => 150.0, 'sigma_cb' => 13.5, 'sigma_cr' => 15.0],
                    ['name' => 'light_neutral', 'cb' => 106.0, 'cr' => 151.0, 'sigma_cb' => 14.5, 'sigma_cr' => 16.0],
                    ['name' => 'light_warm', 'cb' => 99.0, 'cr' => 162.0, 'sigma_cb' => 16.0, 'sigma_cr' => 18.0],
                    ['name' => 'golden', 'cb' => 105.0, 'cr' => 158.0, 'sigma_cb' => 17.5, 'sigma_cr' => 18.5],
                    ['name' => 'medium_neutral', 'cb' => 111.0, 'cr' => 155.0, 'sigma_cb' => 17.0, 'sigma_cr' => 19.0],
                    ['name' => 'olive', 'cb' => 107.0, 'cr' => 145.0, 'sigma_cb' => 17.0, 'sigma_cr' => 16.5],
                    ['name' => 'brown', 'cb' => 116.0, 'cr' => 151.0, 'sigma_cb' => 18.5, 'sigma_cr' => 19.5],
                    ['name' => 'deep_brown', 'cb' => 121.0, 'cr' => 146.0, 'sigma_cb' => 20.0, 'sigma_cr' => 20.0],
                    ['name' => 'deep_cool', 'cb' => 126.0, 'cr' => 139.0, 'sigma_cb' => 20.0, 'sigma_cr' => 20.0],
                    ['name' => 'very_deep', 'cb' => 125.0, 'cr' => 141.0, 'sigma_cb' => 21.0, 'sigma_cr' => 21.0],
                    ['name' => 'rosy', 'cb' => 103.0, 'cr' => 169.0, 'sigma_cb' => 17.5, 'sigma_cr' => 18.5],
                    ['name' => 'mixed_warm_cool', 'cb' => 113.0, 'cr' => 158.0, 'sigma_cb' => 20.0, 'sigma_cr' => 21.0],
                    ['name' => 'low_light_mixed', 'cb' => 121.0, 'cr' => 149.0, 'sigma_cb' => 22.0, 'sigma_cr' => 22.0],
                ],
                'weights' => [
                    'coverage' => 0.185,
                    'large_regions' => 0.135,
                    'body_span' => 0.085,
                    'centrality' => 0.060,
                    'anatomical_patterns' => 0.225,
                    'multi_person_activity' => 0.095,
                    'texture' => 0.040,
                    'soft_skin' => 0.025,
                    'component_quality' => 0.055,
                    'spatial_topology' => 0.075,
                ],
            ],
            'processing' => [
                'blur_radius' => 22.0,
                'blur_sigma' => 12.0,
                'pixelate_before_blur' => true,
                'pixel_size' => 22,
                'strip_metadata' => true,
            ],
            'tokens' => [
                'enabled' => true,
                'file' => 'tokens.json',
                'max_entries' => 1000,
                'similarity_enabled' => true,
                'max_hamming_distance' => 5,
                'max_score_adjustment' => 0.016,
                'only_use_high_confidence_neighbors' => true,
            ],
            'security' => [
                'allowed_schemes' => ['http', 'https'],
                'allow_private_networks' => false,
                'allow_redirects' => false,
                'user_agent' => 'Jibay_NSFW-1/5.0',
            ],
        ];
    }

    public static function load(): self
    {
        $defaults = self::defaults();
        $configExists = is_file(self::CONFIG_FILE);
        $existing = AtomicJsonFile::read(self::CONFIG_FILE);

        if ($configExists && $existing === null) {
            JsonResponse::error(
                'CONFIG_INVALID',
                'config.json exists but does not contain valid JSON.',
                500
            );
        }

        if ($existing === null) {
            if (!AtomicJsonFile::write(self::CONFIG_FILE, $defaults)) {
                JsonResponse::error(
                    'CONFIG_CREATE_FAILED',
                    'config.json could not be created. Make sure the script directory is writable.',
                    500
                );
            }
            $data = $defaults;
        } else {
            ModelIdentity::assertDocument($existing, 'config.json');
            $legacyVersion = (int)($existing['version'] ?? 0);
            $legacyRevision = (string)($existing['analyzer_revision'] ?? '');
            $data = self::mergeRecursive($defaults, $existing);

            if ($legacyVersion < ModelIdentity::ENGINE_VERSION || $legacyRevision !== ModelIdentity::ANALYZER_REVISION) {
                 
                $data['version'] = ModelIdentity::ENGINE_VERSION;
                $data['analyzer_revision'] = ModelIdentity::ANALYZER_REVISION;
                $data['model_name'] = ModelIdentity::NAME;
                $data['model_name_sha256'] = ModelIdentity::NAME_SHA256;
                $data['analysis'] = $defaults['analysis'];
                $data['limits']['analysis_max_side'] = $defaults['limits']['analysis_max_side'];
                $data['tokens']['max_score_adjustment'] = $defaults['tokens']['max_score_adjustment'];
                $data['security']['user_agent'] = $defaults['security']['user_agent'];
                AtomicJsonFile::write(self::CONFIG_FILE, $data);
            }
        }

        ModelIdentity::assertDocument($data, 'config.json');
        self::validate($data);
        return new self($data);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function resolvePath(string $path): string
    {
        if ($path === '') {
            return __DIR__;
        }

        if (self::isAbsolutePath($path)) {
            return rtrim($path, '/\\');
        }

        return __DIR__ . DIRECTORY_SEPARATOR . trim($path, '/\\');
    }

    private static function mergeRecursive(array $defaults, array $custom): array
    {
        foreach ($custom as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && !array_is_list($value)) {
                $defaults[$key] = self::mergeRecursive($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }
        return $defaults;
    }

    private static function validate(array &$data): void
    {
        $mode = (int)($data['mode'] ?? 1);
        $data['mode'] = in_array($mode, [1, 2], true) ? $mode : 1;

        $data['limits']['max_file_bytes'] = self::clampInt((int)$data['limits']['max_file_bytes'], 262144, 52428800);
        $data['limits']['max_image_pixels'] = self::clampInt((int)$data['limits']['max_image_pixels'], 1000000, 100000000);
        $data['limits']['download_timeout_seconds'] = self::clampInt((int)$data['limits']['download_timeout_seconds'], 3, 60);
        $data['limits']['analysis_max_side'] = self::clampInt((int)$data['limits']['analysis_max_side'], 160, 640);

        $data['analysis']['explicit_threshold'] = self::clampFloat((float)$data['analysis']['explicit_threshold'], 0.35, 0.90);
        $data['analysis']['skin_mask_threshold'] = self::clampFloat((float)$data['analysis']['skin_mask_threshold'], 0.25, 0.80);
        $data['analysis']['soft_skin_threshold'] = self::clampFloat((float)$data['analysis']['soft_skin_threshold'], 0.15, 0.70);
        $data['analysis']['minimum_component_ratio'] = self::clampFloat((float)$data['analysis']['minimum_component_ratio'], 0.0002, 0.03);
        $data['analysis']['morphology_iterations'] = self::clampInt((int)$data['analysis']['morphology_iterations'], 0, 2);

        $data['storage']['jpeg_quality'] = self::clampInt((int)$data['storage']['jpeg_quality'], 50, 100);
        $data['processing']['blur_radius'] = self::clampFloat((float)$data['processing']['blur_radius'], 2.0, 50.0);
        $data['processing']['blur_sigma'] = self::clampFloat((float)$data['processing']['blur_sigma'], 1.0, 30.0);
        $data['processing']['pixel_size'] = self::clampInt((int)$data['processing']['pixel_size'], 4, 64);

        $data['tokens']['max_entries'] = self::clampInt((int)$data['tokens']['max_entries'], 10, 5000);
        $data['tokens']['max_hamming_distance'] = self::clampInt((int)$data['tokens']['max_hamming_distance'], 0, 16);
        $data['tokens']['max_score_adjustment'] = self::clampFloat((float)$data['tokens']['max_score_adjustment'], 0.0, 0.08);

        if (!is_array($data['analysis']['tone_profiles']) || $data['analysis']['tone_profiles'] === []) {
            $data['analysis']['tone_profiles'] = self::defaults()['analysis']['tone_profiles'];
        }
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }

    private static function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private static function clampFloat(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}

final class UrlImageDownloader
{
    public static function download(string $url, AppConfig $config): string
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            JsonResponse::error('INVALID_URL', 'A valid image URL is required.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $allowedSchemes = array_map('strtolower', (array)$config->get('security.allowed_schemes', ['http', 'https']));

        if ($host === '' || !in_array($scheme, $allowedSchemes, true)) {
            JsonResponse::error('UNSUPPORTED_URL', 'The URL scheme is not allowed.');
        }

        if (!(bool)$config->get('security.allow_private_networks', false)) {
            self::assertPublicHost($host);
        }

        $timeout = (int)$config->get('limits.download_timeout_seconds', 20);
        $maxBytes = (int)$config->get('limits.max_file_bytes', 12582912);
        $followRedirects = (bool)$config->get('security.allow_redirects', false);
        $userAgent = (string)$config->get('security.user_agent', 'LocalExplicitImageModerator/3.0');

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'follow_location' => $followRedirects ? 1 : 0,
                'max_redirects' => $followRedirects ? 3 : 0,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'User-Agent: ' . $userAgent,
                    'Accept: image/avif,image/webp,image/png,image/jpeg,image/gif,*/*;q=0.2',
                    'Connection: close',
                ]) . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
            ],
        ]);

        $handle = @fopen($url, 'rb', false, $context);
        if ($handle === false) {
            JsonResponse::error('DOWNLOAD_FAILED', 'The remote image could not be downloaded.');
        }

        $meta = stream_get_meta_data($handle);
        $headers = is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];
        $status = self::extractLastHttpStatus($headers);

        if (!$followRedirects && $status >= 300 && $status < 400) {
            fclose($handle);
            JsonResponse::error('REDIRECT_NOT_ALLOWED', 'Redirects are disabled. Submit the final image URL.');
        }

        if ($status < 200 || $status >= 300) {
            fclose($handle);
            JsonResponse::error('REMOTE_HTTP_ERROR', 'The remote server did not return a successful response.', 400, ['http_status' => $status]);
        }

        $declaredLength = self::headerValue($headers, 'content-length');
        if ($declaredLength !== null && ctype_digit($declaredLength) && (int)$declaredLength > $maxBytes) {
            fclose($handle);
            JsonResponse::error('IMAGE_TOO_LARGE', 'The remote image exceeds the configured size limit.', 413);
        }

        $contentType = strtolower((string)(self::headerValue($headers, 'content-type') ?? ''));
        if ($contentType !== '' && !str_starts_with($contentType, 'image/') && !str_contains($contentType, 'octet-stream')) {
            fclose($handle);
            JsonResponse::error('REMOTE_FILE_NOT_IMAGE', 'The remote resource does not appear to be an image.', 415);
        }

        $data = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                fclose($handle);
                JsonResponse::error('DOWNLOAD_READ_FAILED', 'The image download was interrupted.');
            }
            $data .= $chunk;
            if (strlen($data) > $maxBytes) {
                fclose($handle);
                JsonResponse::error('IMAGE_TOO_LARGE', 'The image exceeds the configured size limit.', 413);
            }
        }
        fclose($handle);

        if ($data === '') {
            JsonResponse::error('EMPTY_IMAGE', 'The downloaded image is empty.');
        }

        return $data;
    }

    private static function extractLastHttpStatus(array $headers): int
    {
        $status = 0;
        foreach ($headers as $header) {
            if (is_string($header) && preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $match)) {
                $status = (int)$match[1];
            }
        }
        return $status;
    }

    private static function headerValue(array $headers, string $name): ?string
    {
        $needle = strtolower($name) . ':';
        $value = null;
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }
            if (str_starts_with(strtolower($header), $needle)) {
                $value = trim(substr($header, strlen($needle)));
            }
        }
        return $value;
    }

    private static function assertPublicHost(string $host): void
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            JsonResponse::error('PRIVATE_HOST_BLOCKED', 'Private and local hosts are not allowed.');
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (!is_array($records) || $records === []) {
                JsonResponse::error('DNS_LOOKUP_FAILED', 'The image host could not be resolved.');
            }

            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = (string)$record['ip'];
                }
                if (isset($record['ipv6'])) {
                    $ips[] = (string)$record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            JsonResponse::error('DNS_LOOKUP_FAILED', 'The image host did not resolve to a usable address.');
        }

        foreach (array_unique($ips) as $ip) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                JsonResponse::error('PRIVATE_IP_BLOCKED', 'The image host resolves to a private or reserved address.');
            }
        }
    }
}

final class PixelGrid
{
    public int $width;
    public int $height;
     
    public array $r;
     
    public array $g;
     
    public array $b;

    public function __construct(int $width, int $height, array $r, array $g, array $b)
    {
        $this->width = $width;
        $this->height = $height;
        $this->r = $r;
        $this->g = $g;
        $this->b = $b;
    }

    public function count(): int
    {
        return $this->width * $this->height;
    }
}

final class RasterImage
{
    private mixed $image;
    private string $engine;
    private int $width;
    private int $height;

    private function __construct(mixed $image, string $engine, int $width, int $height)
    {
        $this->image = $image;
        $this->engine = $engine;
        $this->width = $width;
        $this->height = $height;
    }

    public static function fromBlob(string $data, AppConfig $config): self
    {
        if (extension_loaded('imagick')) {
            try {
                return self::fromImagick($data, $config);
            } catch (Throwable $e) {
                if (!extension_loaded('gd')) {
                    JsonResponse::error('INVALID_IMAGE', 'The file is invalid or its image format is not supported.', 415);
                }
            }
        }

        if (extension_loaded('gd')) {
            return self::fromGd($data, $config);
        }

        JsonResponse::error('IMAGE_EXTENSION_MISSING', 'Imagick or GD must be enabled on the server.', 500);
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function engine(): string
    {
        return $this->engine;
    }

    public function analysisGrid(int $maxSide): PixelGrid
    {
        $scale = min(1.0, $maxSide / max($this->width, $this->height));
        $w = max(1, (int)round($this->width * $scale));
        $h = max(1, (int)round($this->height * $scale));

        if ($this->engine === 'imagick') {
            return $this->imagickGrid($w, $h);
        }
        return $this->gdGrid($w, $h);
    }

    public function save(string $path, bool $explicit, AppConfig $config): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            JsonResponse::error('STORAGE_CREATE_FAILED', 'The configured storage directory could not be created.', 500);
        }

        if ($this->engine === 'imagick') {
            $this->saveImagick($path, $explicit, $config);
            return;
        }

        $this->saveGd($path, $explicit, $config);
    }

    private static function fromImagick(string $data, AppConfig $config): self
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 320 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);

        $probe = new Imagick();
        $probe->pingImageBlob($data);
        $width = (int)$probe->getImageWidth();
        $height = (int)$probe->getImageHeight();
        $format = strtoupper((string)$probe->getImageFormat());
        $probe->clear();
        $probe->destroy();

        self::assertImage($width, $height, $format, $config);

        $image = new Imagick();
        $image->readImageBlob($data);
        if ($image->getNumberImages() > 1) {
            $image->setIteratorIndex(0);
            $first = $image->getImage();
            $image->clear();
            $image->destroy();
            $image = $first;
        }

        if (method_exists($image, 'autoOrientImage')) {
            $image->autoOrientImage();
        }
        $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $image->setIteratorIndex(0);

        return new self($image, 'imagick', (int)$image->getImageWidth(), (int)$image->getImageHeight());
    }

    private static function fromGd(string $data, AppConfig $config): self
    {
        $info = @getimagesizefromstring($data);
        if (!is_array($info)) {
            JsonResponse::error('INVALID_IMAGE', 'The file is invalid or its image format is not supported.', 415);
        }

        $width = (int)($info[0] ?? 0);
        $height = (int)($info[1] ?? 0);
        $format = isset($info['mime']) ? strtoupper((string)$info['mime']) : 'UNKNOWN';
        self::assertImage($width, $height, $format, $config);

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            JsonResponse::error('INVALID_IMAGE', 'The file is invalid or its image format is not supported.', 415);
        }

        return new self($image, 'gd', imagesx($image), imagesy($image));
    }

    private static function assertImage(int $width, int $height, string $format, AppConfig $config): void
    {
        if ($width < 8 || $height < 8) {
            JsonResponse::error('IMAGE_TOO_SMALL', 'The image is too small to analyze.', 415);
        }

        $pixels = $width * $height;
        if ($pixels > (int)$config->get('limits.max_image_pixels', 40000000)) {
            JsonResponse::error('IMAGE_PIXEL_LIMIT_EXCEEDED', 'The image exceeds the configured pixel limit.', 413);
        }

        $allowed = ['JPEG', 'JPG', 'PNG', 'WEBP', 'GIF', 'AVIF', 'HEIC', 'HEIF', 'IMAGE/JPEG', 'IMAGE/PNG', 'IMAGE/WEBP', 'IMAGE/GIF', 'IMAGE/AVIF'];
        if ($format !== '' && $format !== 'UNKNOWN' && !in_array($format, $allowed, true)) {
            JsonResponse::error('UNSUPPORTED_IMAGE_FORMAT', 'The image format is not supported.', 415, ['format' => $format]);
        }
    }

    private function imagickGrid(int $w, int $h): PixelGrid
    {
         
        $copy = clone $this->image;
        $copy->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $copy->resizeImage($w, $h, Imagick::FILTER_TRIANGLE, 1.0, true);
        $copy->setImagePage(0, 0, 0, 0);

        $r = [];
        $g = [];
        $b = [];

        try {
            $pixels = $copy->exportImagePixels(0, 0, $w, $h, 'RGB', Imagick::PIXEL_CHAR);
            $count = count($pixels);
            for ($i = 0; $i + 2 < $count; $i += 3) {
                $r[] = (int)$pixels[$i];
                $g[] = (int)$pixels[$i + 1];
                $b[] = (int)$pixels[$i + 2];
            }
        } catch (Throwable $e) {
            foreach ($copy->getPixelIterator() as $row) {
                foreach ($row as $pixel) {
                    $color = $pixel->getColor();
                    $r[] = (int)$color['r'];
                    $g[] = (int)$color['g'];
                    $b[] = (int)$color['b'];
                }
            }
        }

        $copy->clear();
        $copy->destroy();
        return new PixelGrid($w, $h, $r, $g, $b);
    }

    private function gdGrid(int $w, int $h): PixelGrid
    {
        $copy = imagecreatetruecolor($w, $h);
        if ($copy === false) {
            JsonResponse::error('IMAGE_PROCESSING_FAILED', 'A temporary image buffer could not be created.', 500);
        }
        imagealphablending($copy, true);
        $white = imagecolorallocate($copy, 255, 255, 255);
        imagefilledrectangle($copy, 0, 0, $w, $h, $white);
        imagecopyresampled($copy, $this->image, 0, 0, 0, 0, $w, $h, $this->width, $this->height);

        $r = [];
        $g = [];
        $b = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($copy, $x, $y);
                $r[] = ($rgb >> 16) & 0xFF;
                $g[] = ($rgb >> 8) & 0xFF;
                $b[] = $rgb & 0xFF;
            }
        }

        imagedestroy($copy);
        return new PixelGrid($w, $h, $r, $g, $b);
    }

    private function saveImagick(string $path, bool $explicit, AppConfig $config): void
    {
         
        $output = clone $this->image;
        $output->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $output->setImageBackgroundColor('white');
        if (method_exists($output, 'setImageAlphaChannel')) {
            try {
                $output->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            } catch (Throwable $e) {
                 
            }
        }

        if ($explicit) {
            if ((bool)$config->get('processing.pixelate_before_blur', true)) {
                $pixelSize = (int)$config->get('processing.pixel_size', 20);
                $smallW = max(1, (int)ceil($output->getImageWidth() / $pixelSize));
                $smallH = max(1, (int)ceil($output->getImageHeight() / $pixelSize));
                $output->resizeImage($smallW, $smallH, Imagick::FILTER_BOX, 1.0, true);
                $output->resizeImage($this->width, $this->height, Imagick::FILTER_POINT, 1.0, true);
            }

            $output->gaussianBlurImage(
                (float)$config->get('processing.blur_radius', 20.0),
                (float)$config->get('processing.blur_sigma', 11.0)
            );
        }

        if ((bool)$config->get('processing.strip_metadata', true)) {
            $output->stripImage();
        }
        $output->setImageFormat('jpeg');
        $output->setImageCompression(Imagick::COMPRESSION_JPEG);
        $output->setImageCompressionQuality((int)$config->get('storage.jpeg_quality', 91));
        $output->setInterlaceScheme(Imagick::INTERLACE_JPEG);

        if (!$output->writeImage($path)) {
            $output->clear();
            $output->destroy();
            JsonResponse::error('IMAGE_SAVE_FAILED', 'The processed image could not be saved.', 500);
        }

        $output->clear();
        $output->destroy();
    }

    private function saveGd(string $path, bool $explicit, AppConfig $config): void
    {
        $output = imagecreatetruecolor($this->width, $this->height);
        if ($output === false) {
            JsonResponse::error('IMAGE_PROCESSING_FAILED', 'An output image buffer could not be created.', 500);
        }

        $white = imagecolorallocate($output, 255, 255, 255);
        imagefilledrectangle($output, 0, 0, $this->width, $this->height, $white);
        imagecopy($output, $this->image, 0, 0, 0, 0, $this->width, $this->height);

        if ($explicit) {
            if ((bool)$config->get('processing.pixelate_before_blur', true)) {
                $pixelSize = (int)$config->get('processing.pixel_size', 20);
                imagefilter($output, IMG_FILTER_PIXELATE, $pixelSize, true);
            }
            $passes = max(6, min(32, (int)round((float)$config->get('processing.blur_radius', 20.0))));
            for ($i = 0; $i < $passes; $i++) {
                imagefilter($output, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }

        $saved = imagejpeg($output, $path, (int)$config->get('storage.jpeg_quality', 91));
        imagedestroy($output);
        if (!$saved) {
            JsonResponse::error('IMAGE_SAVE_FAILED', 'The processed image could not be saved.', 500);
        }
    }
}

final class AnalysisResult
{
    public float $score;
    public bool $explicit;
    public string $confidence;
    public array $features;
    public array $signals;
    public string $dhash;
    public string $featureToken;

    public function __construct(
        float $score,
        bool $explicit,
        string $confidence,
        array $features,
        array $signals,
        string $dhash,
        string $featureToken
    ) {
        $this->score = $score;
        $this->explicit = $explicit;
        $this->confidence = $confidence;
        $this->features = $features;
        $this->signals = $signals;
        $this->dhash = $dhash;
        $this->featureToken = $featureToken;
    }
}

final class ExplicitImageAnalyzer
{
    private AppConfig $config;
    private int $w = 0;
    private int $h = 0;
    private int $n = 0;
     
    private array $skinProbability = [];
     
    private array $mask = [];
     
    private array $luma = [];
     
    private array $chroma = [];
     
    private array $whiteMask = [];
     
    private array $neutralMask = [];
     
    private array $chromaticSkinMask = [];

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }

    public function analyze(PixelGrid $grid): AnalysisResult
    {
        $this->w = $grid->width;
        $this->h = $grid->height;
        $this->n = $grid->count();

        [$gainR, $gainG, $gainB] = $this->estimateWhiteBalance($grid);
        $hardThreshold = (float)$this->config->get('analysis.skin_mask_threshold', 0.49);
        $softThreshold = (float)$this->config->get('analysis.soft_skin_threshold', 0.36);

        $hardCount = 0;
        $softCount = 0;
        $skinProbSum = 0.0;
        $globalLumaSum = 0.0;
        $globalLumaSq = 0.0;
        $globalChromaSum = 0.0;
        $globalChromaSq = 0.0;
        $whiteCount = 0;
        $neutralCount = 0;
        $chromaticCandidateCount = 0;
        $whiteThreshold = (float)$this->config->get('analysis.white_pixel_threshold', 0.58);
        $neutralThreshold = (float)$this->config->get('analysis.neutral_pixel_threshold', 0.70);
        $chromaticThreshold = (float)$this->config->get('analysis.chromatic_skin_threshold', 0.28);
        $colorHistogram = array_fill(0, 64, 0);

        for ($i = 0; $i < $this->n; $i++) {
            $r0 = $grid->r[$i];
            $g0 = $grid->g[$i];
            $b0 = $grid->b[$i];

            $r = (int)max(0, min(255, round($r0 * $gainR)));
            $g = (int)max(0, min(255, round($g0 * $gainG)));
            $b = (int)max(0, min(255, round($b0 * $gainB)));

            $original = $this->pixelClassification($r0, $g0, $b0);
            $balanced = $this->pixelClassification($r, $g, $b);
            $prob = max((float)$original['skin'], 0.92 * (float)$balanced['skin']);
            $whiteProbability = max((float)$original['white'], 0.92 * (float)$balanced['white']);
            $neutralProbability = max((float)$original['neutral'], 0.92 * (float)$balanced['neutral']);
            $chromaticEvidence = max((float)$original['chromatic'], 0.90 * (float)$balanced['chromatic']);

             
            if ($whiteProbability >= 0.70 && $chromaticEvidence < 0.48) {
                $prob *= 0.12;
            } elseif ($whiteProbability >= 0.52 && $chromaticEvidence < 0.38) {
                $prob *= 0.38;
            }

            $y = 0.299 * $r0 + 0.587 * $g0 + 0.114 * $b0;
            $maxRgb = max($r0, $g0, $b0);
            $minRgb = min($r0, $g0, $b0);
            $c = $maxRgb - $minRgb;

            $this->skinProbability[$i] = $prob;
            $this->mask[$i] = $prob >= $hardThreshold ? 1 : 0;
            $this->whiteMask[$i] = $whiteProbability >= $whiteThreshold ? 1 : 0;
            $this->neutralMask[$i] = $neutralProbability >= $neutralThreshold ? 1 : 0;
            $this->chromaticSkinMask[$i] = ($prob >= $softThreshold && $chromaticEvidence >= $chromaticThreshold) ? 1 : 0;
            $this->luma[$i] = $y;
            $this->chroma[$i] = $c;

            if ($prob >= $hardThreshold) {
                $hardCount++;
            }
            if ($prob >= $softThreshold) {
                $softCount++;
            }
            if ($this->whiteMask[$i] === 1) {
                $whiteCount++;
            }
            if ($this->neutralMask[$i] === 1) {
                $neutralCount++;
            }
            if ($this->chromaticSkinMask[$i] === 1) {
                $chromaticCandidateCount++;
            }

            $skinProbSum += $prob;
            $globalLumaSum += $y;
            $globalLumaSq += $y * $y;
            $globalChromaSum += $c;
            $globalChromaSq += $c * $c;

            $bin = (($r0 >> 6) << 4) | (($g0 >> 6) << 2) | ($b0 >> 6);
            $colorHistogram[$bin]++;
        }

        $iterations = (int)$this->config->get('analysis.morphology_iterations', 1);
        for ($i = 0; $i < $iterations; $i++) {
            $this->mask = $this->erode($this->dilate($this->mask));
        }

         
        $hardCount = array_sum($this->mask);
        $chromaticSkinCount = 0;
        for ($i = 0; $i < $this->n; $i++) {
            if ($this->mask[$i] === 1 && $this->chromaticSkinMask[$i] === 1) {
                $chromaticSkinCount++;
            }
        }

        $components = $this->connectedComponents();
        $regionFeatures = $this->regionFeatures();
        $pairFeatures = $this->pairedShapeFeatures($components);
        $edgeFeatures = $this->edgeAndTextureFeatures();
        $topologyFeatures = $this->spatialTopologyFeatures();
        $neutralFeatures = $this->neutralSurfaceFeatures();

        $skinRatio = $hardCount / max(1, $this->n);
        $softSkinRatio = $softCount / max(1, $this->n);
        $chromaticSkinRatio = $chromaticSkinCount / max(1, $this->n);
        $whiteRatio = $whiteCount / max(1, $this->n);
        $neutralRatio = $neutralCount / max(1, $this->n);
        $meanSkinProbability = $skinProbSum / max(1, $this->n);

        $largest = $components[0] ?? null;
        $second = $components[1] ?? null;
        $largestRatio = $largest !== null ? $largest['area'] / $this->n : 0.0;
        $secondRatio = $second !== null ? $second['area'] / $this->n : 0.0;
        $substantialCount = 0;
        $qualitySum = 0.0;
        $bodySpan = 0.0;

        foreach ($components as $component) {
            $ratio = $component['area'] / $this->n;
            if ($ratio >= 0.012) {
                $substantialCount++;
            }
            $qualitySum += min(1.0, $component['fill_ratio'] * 1.5) * min(1.0, $component['mean_probability'] / 0.68) * min(1.0, $ratio / 0.08);
            $bodySpan = max($bodySpan, $component['height'] / $this->h);
        }
        $componentQuality = min(1.0, $qualitySum / 2.2);

        $globalLumaMean = $globalLumaSum / max(1, $this->n);
        $globalLumaStd = sqrt(max(0.0, $globalLumaSq / max(1, $this->n) - $globalLumaMean * $globalLumaMean));
        $globalChromaMean = $globalChromaSum / max(1, $this->n);
        $globalChromaStd = sqrt(max(0.0, $globalChromaSq / max(1, $this->n) - $globalChromaMean * $globalChromaMean));
        $colorEntropy = $this->entropy($colorHistogram, $this->n) / 6.0;

        $coverageSignal = $this->smoothStep(0.09, 0.54, $skinRatio) * 0.72
            + $this->smoothStep(0.16, 0.72, $softSkinRatio) * 0.28;
        $largeRegionsSignal = min(1.0,
            0.70 * $this->smoothStep(0.045, 0.34, $largestRatio)
            + 0.30 * $this->smoothStep(0.018, 0.18, $secondRatio)
        );
        $bodySpanSignal = min(1.0,
            0.75 * $this->smoothStep(0.30, 0.92, $bodySpan)
            + 0.25 * $this->smoothStep(0.12, 0.55, $regionFeatures['vertical_skin_span'])
        );
        $centralitySignal = min(1.0,
            0.58 * $this->smoothStep(0.08, 0.53, $regionFeatures['center_ratio'])
            + 0.42 * $regionFeatures['center_share_of_skin']
        );

        $anatomicalSignal = min(1.0,
            0.36 * $pairFeatures['chest_pair']
            + 0.42 * $pairFeatures['lower_pair']
            + 0.22 * $pairFeatures['central_lower_focus']
        );

        $activitySignal = min(1.0,
            0.38 * $this->smoothStep(1.0, 4.0, (float)$substantialCount)
            + 0.27 * $this->smoothStep(0.16, 0.55, $skinRatio)
            + 0.20 * $pairFeatures['multi_cluster_interaction']
            + 0.15 * $regionFeatures['cross_quadrant_presence']
        );

        $textureSignal = min(1.0,
            0.48 * $edgeFeatures['skin_edge_density']
            + 0.27 * $edgeFeatures['skin_luma_variation']
            + 0.25 * $colorEntropy
        );

        $softSkinSignal = min(1.0,
            0.65 * $this->smoothStep(0.17, 0.70, $softSkinRatio)
            + 0.35 * $this->smoothStep(0.25, 0.70, $meanSkinProbability)
        );

        $spatialTopologySignal = $topologyFeatures['topology_signal'];

        $whiteGarmentSignal = min(1.0,
            (0.42 * $this->smoothStep(0.22, 0.70, $neutralFeatures['white_torso_ratio'])
            + 0.24 * $this->smoothStep(0.18, 0.64, $neutralFeatures['white_middle_lower_ratio'])
            + 0.18 * $neutralFeatures['white_row_continuity']
            + 0.16 * $this->smoothStep(0.42, 0.88, $neutralFeatures['neutral_torso_ratio']))
            * (1.0 - 0.72 * $this->smoothStep(0.15, 0.43, $skinRatio))
            * (1.0 - 0.42 * $anatomicalSignal)
        );
        $achromaticDominanceSignal = min(1.0,
            $this->smoothStep(0.52, 0.90, $neutralRatio)
            * (1.0 - $this->smoothStep(0.08, 0.30, $skinRatio))
        );

        $weights = (array)$this->config->get('analysis.weights', []);
        $score = 0.0;
        $score += (float)($weights['coverage'] ?? 0.245) * $coverageSignal;
        $score += (float)($weights['large_regions'] ?? 0.165) * $largeRegionsSignal;
        $score += (float)($weights['body_span'] ?? 0.105) * $bodySpanSignal;
        $score += (float)($weights['centrality'] ?? 0.080) * $centralitySignal;
        $score += (float)($weights['anatomical_patterns'] ?? 0.170) * $anatomicalSignal;
        $score += (float)($weights['multi_person_activity'] ?? 0.100) * $activitySignal;
        $score += (float)($weights['texture'] ?? 0.055) * $textureSignal;
        $score += (float)($weights['soft_skin'] ?? 0.040) * $softSkinSignal;
        $score += (float)($weights['component_quality'] ?? 0.055) * $componentQuality;
        $score += (float)($weights['spatial_topology'] ?? 0.090) * $spatialTopologySignal;

        $penalties = $this->falsePositivePenalties(
            $skinRatio,
            $softSkinRatio,
            $largestRatio,
            $substantialCount,
            $regionFeatures,
            $globalLumaStd,
            $globalChromaStd,
            $colorEntropy,
            $edgeFeatures,
            $topologyFeatures,
            $neutralFeatures,
            $whiteGarmentSignal,
            $achromaticDominanceSignal,
            $chromaticSkinRatio
        );

        $positiveVotes = 0;
        foreach ([
            $coverageSignal >= 0.56,
            $largeRegionsSignal >= 0.54,
            $bodySpanSignal >= 0.52,
            $anatomicalSignal >= 0.50,
            $activitySignal >= 0.52,
            $spatialTopologySignal >= 0.55,
            $chromaticSkinRatio >= 0.10,
        ] as $vote) {
            if ($vote) {
                $positiveVotes++;
            }
        }
        $consensusStrength = $this->smoothStep(3.0, 6.0, (float)$positiveVotes)
            * $this->smoothStep(0.11, 0.42, $skinRatio)
            * (1.0 - 0.65 * $whiteGarmentSignal);
        $consensusBonus = (float)$this->config->get('analysis.consensus_bonus_max', 0.040) * $consensusStrength;

        $score += $consensusBonus;
        $score -= $penalties['total'];
        $score = max(0.0, min(1.0, $score));

        $minimumSkinRatio = (float)$this->config->get('analysis.minimum_skin_ratio_for_explicit', 0.055);
        $minimumChromaticRatio = (float)$this->config->get('analysis.minimum_chromatic_skin_ratio', 0.032);
        $minimumVotes = (int)$this->config->get('analysis.minimum_positive_votes', 3);
        $strongAnatomicalEvidence = $anatomicalSignal >= 0.70 && $skinRatio >= 0.09;
        $strongActivityEvidence = $activitySignal >= 0.70 && $skinRatio >= 0.14;
        $skinEvidenceGate = $skinRatio >= $minimumSkinRatio && $chromaticSkinRatio >= $minimumChromaticRatio;
        $consensusGate = $positiveVotes >= $minimumVotes || $strongAnatomicalEvidence || $strongActivityEvidence;
        $whiteGuardPassed = true;
        if ((bool)$this->config->get('analysis.white_garment_guard', true)) {
            $whiteGuardPassed = !(
                $whiteGarmentSignal >= 0.56
                && $skinRatio < 0.24
                && $anatomicalSignal < 0.72
                && $regionFeatures['lower_ratio'] < 0.18
            );
        }
        $decisionGatePassed = $skinEvidenceGate && $consensusGate && $whiteGuardPassed;

        $features = [
            'skin_ratio' => $skinRatio,
            'soft_skin_ratio' => $softSkinRatio,
            'chromatic_skin_ratio' => $chromaticSkinRatio,
            'white_pixel_ratio' => $whiteRatio,
            'neutral_pixel_ratio' => $neutralRatio,
            'mean_skin_probability' => $meanSkinProbability,
            'largest_component_ratio' => $largestRatio,
            'second_component_ratio' => $secondRatio,
            'component_count' => count($components),
            'substantial_component_count' => $substantialCount,
            'component_quality' => $componentQuality,
            'body_span' => $bodySpan,
            'center_skin_ratio' => $regionFeatures['center_ratio'],
            'upper_skin_ratio' => $regionFeatures['upper_ratio'],
            'middle_skin_ratio' => $regionFeatures['middle_ratio'],
            'lower_skin_ratio' => $regionFeatures['lower_ratio'],
            'chest_pair_signal' => $pairFeatures['chest_pair'],
            'lower_pair_signal' => $pairFeatures['lower_pair'],
            'central_lower_signal' => $pairFeatures['central_lower_focus'],
            'multi_cluster_signal' => $pairFeatures['multi_cluster_interaction'],
            'skin_edge_density' => $edgeFeatures['skin_edge_density'],
            'skin_luma_variation' => $edgeFeatures['skin_luma_variation'],
            'occupied_grid_ratio' => $topologyFeatures['occupied_cell_ratio'],
            'dense_grid_ratio' => $topologyFeatures['dense_cell_ratio'],
            'vertical_grid_continuity' => $topologyFeatures['vertical_continuity'],
            'center_grid_density' => $topologyFeatures['center_density'],
            'lower_center_grid_density' => $topologyFeatures['lower_center_density'],
            'border_skin_share' => $topologyFeatures['border_skin_share'],
            'grid_density_std' => $topologyFeatures['cell_density_std'],
            'global_luma_std' => $globalLumaStd / 128.0,
            'global_chroma_std' => $globalChromaStd / 128.0,
            'color_entropy' => $colorEntropy,
            'consensus_bonus' => $consensusBonus,
            'positive_vote_count' => $positiveVotes,
            'white_torso_ratio' => $neutralFeatures['white_torso_ratio'],
            'white_middle_lower_ratio' => $neutralFeatures['white_middle_lower_ratio'],
            'neutral_torso_ratio' => $neutralFeatures['neutral_torso_ratio'],
            'white_row_continuity' => $neutralFeatures['white_row_continuity'],
            'white_garment_signal' => $whiteGarmentSignal,
            'achromatic_dominance_signal' => $achromaticDominanceSignal,
            'skin_evidence_gate' => $skinEvidenceGate,
            'consensus_gate' => $consensusGate,
            'white_guard_passed' => $whiteGuardPassed,
            'decision_gate_passed' => $decisionGatePassed,
            'false_positive_penalty' => $penalties['total'],
        ];

        $signals = [
            'coverage' => $coverageSignal,
            'large_regions' => $largeRegionsSignal,
            'body_span' => $bodySpanSignal,
            'centrality' => $centralitySignal,
            'anatomical_patterns' => $anatomicalSignal,
            'multi_person_activity' => $activitySignal,
            'texture' => $textureSignal,
            'soft_skin' => $softSkinSignal,
            'component_quality' => $componentQuality,
            'spatial_topology' => $spatialTopologySignal,
            'white_garment' => $whiteGarmentSignal,
            'achromatic_dominance' => $achromaticDominanceSignal,
            'consensus_bonus' => $consensusBonus,
            'penalties' => $penalties,
        ];

        $threshold = (float)$this->config->get('analysis.explicit_threshold', 0.665);
        $explicit = $score >= $threshold && $decisionGatePassed;
        if (!$explicit && $score >= $threshold) {
            $score = max(0.0, $threshold - 0.001);
        }
        $confidence = $this->confidenceLabel($score, $threshold);
        $dhash = $this->differenceHash($grid);
        $featureToken = $this->makeFeatureToken($features);

        return new AnalysisResult($score, $explicit, $confidence, $features, $signals, $dhash, $featureToken);
    }

    private function estimateWhiteBalance(PixelGrid $grid): array
    {
        if (!(bool)$this->config->get('analysis.adaptive_white_balance', true)) {
            return [1.0, 1.0, 1.0];
        }

        $sumR = 0.0;
        $sumG = 0.0;
        $sumB = 0.0;
        $count = 0;
        $step = max(1, intdiv($grid->count(), 12000));

        for ($i = 0; $i < $grid->count(); $i += $step) {
            $r = $grid->r[$i];
            $g = $grid->g[$i];
            $b = $grid->b[$i];
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);

            if ($max < 28 || $min > 245 || ($max - $min) > 150) {
                continue;
            }

            $sumR += $r;
            $sumG += $g;
            $sumB += $b;
            $count++;
        }

        if ($count < 50) {
            return [1.0, 1.0, 1.0];
        }

        $meanR = $sumR / $count;
        $meanG = $sumG / $count;
        $meanB = $sumB / $count;
        $gray = ($meanR + $meanG + $meanB) / 3.0;

        return [
            max(0.82, min(1.18, $gray / max(1.0, $meanR))),
            max(0.82, min(1.18, $gray / max(1.0, $meanG))),
            max(0.82, min(1.18, $gray / max(1.0, $meanB))),
        ];
    }

    private function pixelClassification(int $r, int $g, int $b): array
    {
        $y = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $cb = 128.0 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b;
        $cr = 128.0 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b;

        [$hue, $sat, $val] = $this->rgbToHsv($r, $g, $b);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $spread = $max - $min;

        $cbcrScore = 0.0;
        foreach ((array)$this->config->get('analysis.tone_profiles', []) as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $name = (string)($profile['name'] ?? '');
            if ($name === 'low_light_mixed' && $y > 165.0) {
                continue;
            }
            if (in_array($name, ['deep_cool', 'very_deep'], true) && $y > 205.0) {
                continue;
            }

            $centerCb = (float)($profile['cb'] ?? 110.0);
            $centerCr = (float)($profile['cr'] ?? 155.0);
            $sigmaCb = max(4.0, min(24.0, (float)($profile['sigma_cb'] ?? 18.0)));
            $sigmaCr = max(4.0, min(24.0, (float)($profile['sigma_cr'] ?? 19.0)));
            $distance = (($cb - $centerCb) / $sigmaCb) ** 2 + (($cr - $centerCr) / $sigmaCr) ** 2;
            $cbcrScore = max($cbcrScore, exp(-0.5 * $distance));
        }

        $hueDistance = min(abs($hue - 20.0), abs($hue - 380.0));
        $hueScore = exp(-0.5 * ($hueDistance / 22.0) ** 2);
        $minimumSat = $val >= 0.78 ? 0.065 : ($val >= 0.45 ? 0.045 : 0.028);
        $satScore = $this->rangeBell($sat, $minimumSat, 0.78, 0.085);
        $valScore = $this->rangeBell($val, 0.045, 0.985, 0.07);
        $hsvScore = $hueScore * $satScore * $valScore;

        $sum = max(1.0, $r + $g + $b);
        $rn = $r / $sum;
        $gn = $g / $sum;
        $normalizedDistance = (($rn - 0.42) / 0.095) ** 2 + (($gn - 0.34) / 0.064) ** 2;
        $normalizedScore = exp(-0.5 * $normalizedDistance);

        $classicLight = ($r > 92 && $g > 38 && $b > 18 && $spread > 11 && $r >= $g && $r >= $b)
            ? min(1.0, ($r - min($g, $b)) / 62.0 + 0.28)
            : 0.0;
        $classicDark = ($y >= 16 && $y <= 178 && $r >= $g * 0.80 && $g >= $b * 0.70 && ($r + $g) > ($b * 1.52))
            ? min(1.0, 0.32 + ($r + $g - 1.52 * $b) / 125.0)
            : 0.0;
        $ruleScore = max($classicLight, $classicDark);

        $redChroma = max(0.0, $cr - 128.0);
        $blueDeficit = max(0.0, 128.0 - $cb);
        $opponentRed = $r - 0.5 * ($g + $b);
        $neutralDistance = hypot($cb - 128.0, $cr - 128.0);
        $warmth = 0.68 * $this->smoothStep(2.0, 34.0, $redChroma)
            + 0.32 * $this->smoothStep(0.0, 27.0, $blueDeficit);
        $chromaticEvidence = min(1.0,
            0.42 * $warmth
            + 0.23 * $this->smoothStep(4.0, 31.0, $neutralDistance)
            + 0.22 * $this->smoothStep($minimumSat, 0.31, $sat)
            + 0.13 * $this->smoothStep(1.0, 32.0, $opponentRed)
        );

        $brightGate = $this->smoothStep(0.67, 0.96, $val);
        $lowSaturation = 1.0 - $this->smoothStep(0.055, 0.205, $sat);
        $lowSpread = 1.0 - $this->smoothStep(9.0, 48.0, (float)$spread);
        $brightFloor = $this->smoothStep(158.0, 238.0, (float)$min);
        $whiteProbability = min(1.0,
            $brightGate * (0.48 * $lowSaturation + 0.30 * $lowSpread + 0.22 * $brightFloor)
        );
        $warmWhite = $this->smoothStep(0.77, 0.97, $val)
            * (1.0 - $this->smoothStep(0.12, 0.27, $sat))
            * $this->smoothStep(172.0, 235.0, (float)$min);
        $whiteProbability = max($whiteProbability, 0.92 * $warmWhite);

        $neutralProbability = min(1.0,
            (1.0 - $this->smoothStep(0.025, 0.145, $sat))
            * $this->smoothStep(0.20, 0.95, $val)
        );

        $lumaGate = $this->rangeBell($y / 255.0, 0.022, 0.988, 0.055);
        $bluePenalty = ($b > $r * 1.16 && $b > $g * 1.10) ? 0.38 : 1.0;
        $greenPenalty = ($g > $r * 1.20 && $g > $b * 1.16) ? 0.44 : 1.0;
        $neutralGate = 0.08 + 0.92 * $this->smoothStep(0.065, 0.37, $chromaticEvidence);
        $lightSaturationGate = $val >= 0.74
            ? 0.08 + 0.92 * $this->smoothStep(0.045, 0.18, $sat)
            : 0.16 + 0.84 * $this->smoothStep(0.025, 0.14, $sat);

        $skinScore = (
            0.46 * $cbcrScore
            + 0.20 * $hsvScore
            + 0.13 * $normalizedScore
            + 0.09 * $ruleScore
            + 0.12 * $chromaticEvidence
        ) * $lumaGate * $bluePenalty * $greenPenalty * $neutralGate * $lightSaturationGate;

        $skinScore *= max(0.03, 1.0 - 0.90 * $whiteProbability);
        if ($neutralProbability > 0.82 && $chromaticEvidence < 0.28) {
            $skinScore *= 0.06;
        }
        if ($val > 0.88 && $sat < 0.075 && $spread < 18) {
            $skinScore *= 0.02;
        }

        return [
            'skin' => max(0.0, min(1.0, $skinScore)),
            'white' => max(0.0, min(1.0, $whiteProbability)),
            'neutral' => max(0.0, min(1.0, $neutralProbability)),
            'chromatic' => max(0.0, min(1.0, $chromaticEvidence)),
        ];
    }

    private function rgbToHsv(int $r, int $g, int $b): array
    {
        $rf = $r / 255.0;
        $gf = $g / 255.0;
        $bf = $b / 255.0;
        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $delta = $max - $min;

        if ($delta <= 0.000001) {
            $h = 0.0;
        } elseif ($max === $rf) {
            $h = 60.0 * fmod((($gf - $bf) / $delta), 6.0);
        } elseif ($max === $gf) {
            $h = 60.0 * ((($bf - $rf) / $delta) + 2.0);
        } else {
            $h = 60.0 * ((($rf - $gf) / $delta) + 4.0);
        }
        if ($h < 0.0) {
            $h += 360.0;
        }

        $s = $max <= 0.0 ? 0.0 : $delta / $max;
        return [$h, $s, $max];
    }

    private function rangeBell(float $value, float $min, float $max, float $softness): float
    {
        if ($value >= $min && $value <= $max) {
            return 1.0;
        }
        $distance = $value < $min ? $min - $value : $value - $max;
        return exp(-0.5 * ($distance / max(0.0001, $softness)) ** 2);
    }

     
    private function dilate(array $source): array
    {
        $out = array_fill(0, $this->n, 0);
        for ($y = 0; $y < $this->h; $y++) {
            $row = $y * $this->w;
            for ($x = 0; $x < $this->w; $x++) {
                $idx = $row + $x;
                if ($source[$idx] === 1) {
                    $out[$idx] = 1;
                    if ($x > 0) $out[$idx - 1] = 1;
                    if ($x + 1 < $this->w) $out[$idx + 1] = 1;
                    if ($y > 0) $out[$idx - $this->w] = 1;
                    if ($y + 1 < $this->h) $out[$idx + $this->w] = 1;
                    if ($x > 0 && $y > 0) $out[$idx - $this->w - 1] = 1;
                    if ($x + 1 < $this->w && $y > 0) $out[$idx - $this->w + 1] = 1;
                    if ($x > 0 && $y + 1 < $this->h) $out[$idx + $this->w - 1] = 1;
                    if ($x + 1 < $this->w && $y + 1 < $this->h) $out[$idx + $this->w + 1] = 1;
                }
            }
        }
        return $out;
    }

     
    private function erode(array $source): array
    {
        $out = array_fill(0, $this->n, 0);
        for ($y = 1; $y + 1 < $this->h; $y++) {
            $row = $y * $this->w;
            for ($x = 1; $x + 1 < $this->w; $x++) {
                $idx = $row + $x;
                if (
                    $source[$idx] === 1 &&
                    $source[$idx - 1] === 1 &&
                    $source[$idx + 1] === 1 &&
                    $source[$idx - $this->w] === 1 &&
                    $source[$idx + $this->w] === 1 &&
                    $source[$idx - $this->w - 1] === 1 &&
                    $source[$idx - $this->w + 1] === 1 &&
                    $source[$idx + $this->w - 1] === 1 &&
                    $source[$idx + $this->w + 1] === 1
                ) {
                    $out[$idx] = 1;
                }
            }
        }
        return $out;
    }

    private function connectedComponents(): array
    {
        $visited = array_fill(0, $this->n, 0);
        $components = [];
        $minArea = max(10, (int)round($this->n * (float)$this->config->get('analysis.minimum_component_ratio', 0.0012)));
        $neighbors = [-1, 1, -$this->w, $this->w, -$this->w - 1, -$this->w + 1, $this->w - 1, $this->w + 1];

        for ($start = 0; $start < $this->n; $start++) {
            if ($this->mask[$start] === 0 || $visited[$start] === 1) {
                continue;
            }

            $queue = [$start];
            $visited[$start] = 1;
            $head = 0;
            $area = 0;
            $sumX = 0.0;
            $sumY = 0.0;
            $sumProb = 0.0;
            $minX = $this->w;
            $maxX = 0;
            $minY = $this->h;
            $maxY = 0;
            $perimeter = 0;

            while ($head < count($queue)) {
                $idx = $queue[$head++];
                $y = intdiv($idx, $this->w);
                $x = $idx - $y * $this->w;

                $area++;
                $sumX += $x;
                $sumY += $y;
                $sumProb += $this->skinProbability[$idx];
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);

                $orthogonalBoundary = 0;
                if ($x === 0 || $this->mask[$idx - 1] === 0) $orthogonalBoundary++;
                if ($x + 1 >= $this->w || $this->mask[$idx + 1] === 0) $orthogonalBoundary++;
                if ($y === 0 || $this->mask[$idx - $this->w] === 0) $orthogonalBoundary++;
                if ($y + 1 >= $this->h || $this->mask[$idx + $this->w] === 0) $orthogonalBoundary++;
                $perimeter += $orthogonalBoundary;

                foreach ($neighbors as $offset) {
                    $next = $idx + $offset;
                    if ($next < 0 || $next >= $this->n || $visited[$next] === 1 || $this->mask[$next] === 0) {
                        continue;
                    }
                    $ny = intdiv($next, $this->w);
                    $nx = $next - $ny * $this->w;
                    if (abs($nx - $x) > 1 || abs($ny - $y) > 1) {
                        continue;
                    }
                    $visited[$next] = 1;
                    $queue[] = $next;
                }
            }

            if ($area < $minArea) {
                continue;
            }

            $width = $maxX - $minX + 1;
            $height = $maxY - $minY + 1;
            $bboxArea = max(1, $width * $height);
            $compactness = 4.0 * M_PI * $area / max(1.0, $perimeter * $perimeter);

            $components[] = [
                'area' => $area,
                'min_x' => $minX,
                'max_x' => $maxX,
                'min_y' => $minY,
                'max_y' => $maxY,
                'width' => $width,
                'height' => $height,
                'centroid_x' => $sumX / $area,
                'centroid_y' => $sumY / $area,
                'fill_ratio' => $area / $bboxArea,
                'mean_probability' => $sumProb / $area,
                'compactness' => max(0.0, min(1.0, $compactness)),
            ];
        }

        usort($components, static fn(array $a, array $b): int => $b['area'] <=> $a['area']);
        return array_slice($components, 0, 30);
    }

    private function regionFeatures(): array
    {
        $regions = [
            'upper' => [0, 0, $this->w, (int)round($this->h * 0.34)],
            'middle' => [0, (int)round($this->h * 0.25), $this->w, (int)round($this->h * 0.72)],
            'lower' => [0, (int)round($this->h * 0.58), $this->w, $this->h],
            'center' => [(int)round($this->w * 0.18), (int)round($this->h * 0.12), (int)round($this->w * 0.82), (int)round($this->h * 0.92)],
        ];

        $result = [];
        $totalSkin = array_sum($this->mask);
        foreach ($regions as $name => [$x0, $y0, $x1, $y1]) {
            $skin = 0;
            $area = max(1, ($x1 - $x0) * ($y1 - $y0));
            for ($y = $y0; $y < $y1; $y++) {
                $offset = $y * $this->w;
                for ($x = $x0; $x < $x1; $x++) {
                    $skin += $this->mask[$offset + $x];
                }
            }
            $result[$name . '_ratio'] = $skin / $area;
            $result[$name . '_share_of_skin'] = $skin / max(1, $totalSkin);
        }

        $occupiedRows = 0;
        $firstRow = $this->h;
        $lastRow = -1;
        $quadrants = [0, 0, 0, 0];

        for ($y = 0; $y < $this->h; $y++) {
            $rowSkin = 0;
            for ($x = 0; $x < $this->w; $x++) {
                if ($this->mask[$y * $this->w + $x] === 1) {
                    $rowSkin++;
                    $q = ($y >= $this->h / 2 ? 2 : 0) + ($x >= $this->w / 2 ? 1 : 0);
                    $quadrants[$q]++;
                }
            }
            if ($rowSkin >= max(2, (int)round($this->w * 0.02))) {
                $occupiedRows++;
                $firstRow = min($firstRow, $y);
                $lastRow = max($lastRow, $y);
            }
        }

        $presentQuadrants = 0;
        foreach ($quadrants as $count) {
            if ($count / max(1, $this->n) >= 0.018) {
                $presentQuadrants++;
            }
        }

        $result['vertical_skin_span'] = $lastRow >= $firstRow ? ($lastRow - $firstRow + 1) / $this->h : 0.0;
        $result['occupied_row_ratio'] = $occupiedRows / $this->h;
        $result['cross_quadrant_presence'] = $presentQuadrants / 4.0;
        return $result;
    }


    private function spatialTopologyFeatures(): array
    {
        $gridSize = (int)$this->config->get('analysis.topology_grid_size', 5);
        $gridSize = max(4, min(8, $gridSize));
        $densities = [];
        $occupied = 0;
        $dense = 0;
        $rowOccupied = array_fill(0, $gridSize, false);
        $centerSkin = 0;
        $centerArea = 0;
        $lowerCenterSkin = 0;
        $lowerCenterArea = 0;

        for ($gy = 0; $gy < $gridSize; $gy++) {
            $y0 = (int)floor($gy * $this->h / $gridSize);
            $y1 = (int)floor(($gy + 1) * $this->h / $gridSize);
            for ($gx = 0; $gx < $gridSize; $gx++) {
                $x0 = (int)floor($gx * $this->w / $gridSize);
                $x1 = (int)floor(($gx + 1) * $this->w / $gridSize);
                $skin = 0;
                $area = max(1, ($x1 - $x0) * ($y1 - $y0));

                for ($y = $y0; $y < $y1; $y++) {
                    $row = $y * $this->w;
                    for ($x = $x0; $x < $x1; $x++) {
                        $skin += $this->mask[$row + $x];
                    }
                }

                $density = $skin / $area;
                $densities[] = $density;
                if ($density >= 0.075) {
                    $occupied++;
                    $rowOccupied[$gy] = true;
                }
                if ($density >= 0.22) {
                    $dense++;
                }

                $isCenterColumn = $gx >= 1 && $gx <= $gridSize - 2;
                $isCenterRow = $gy >= 1 && $gy <= $gridSize - 2;
                if ($isCenterColumn && $isCenterRow) {
                    $centerSkin += $skin;
                    $centerArea += $area;
                }
                if ($isCenterColumn && $gy >= intdiv($gridSize, 2)) {
                    $lowerCenterSkin += $skin;
                    $lowerCenterArea += $area;
                }
            }
        }

        $longest = 0;
        $current = 0;
        foreach ($rowOccupied as $present) {
            if ($present) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
        }

        $mean = array_sum($densities) / max(1, count($densities));
        $variance = 0.0;
        foreach ($densities as $density) {
            $variance += ($density - $mean) ** 2;
        }
        $std = sqrt($variance / max(1, count($densities)));

        $borderWidth = max(1, (int)round(min($this->w, $this->h) * 0.055));
        $borderSkin = 0;
        $totalSkin = max(1, array_sum($this->mask));
        for ($y = 0; $y < $this->h; $y++) {
            $row = $y * $this->w;
            for ($x = 0; $x < $this->w; $x++) {
                if ($x < $borderWidth || $x >= $this->w - $borderWidth || $y < $borderWidth || $y >= $this->h - $borderWidth) {
                    $borderSkin += $this->mask[$row + $x];
                }
            }
        }

        $cellCount = $gridSize * $gridSize;
        $occupiedRatio = $occupied / $cellCount;
        $denseRatio = $dense / $cellCount;
        $verticalContinuity = $longest / $gridSize;
        $centerDensity = $centerSkin / max(1, $centerArea);
        $lowerCenterDensity = $lowerCenterSkin / max(1, $lowerCenterArea);
        $borderSkinShare = min(1.0, $borderSkin / $totalSkin);

        $structuredOccupancy = $this->rangeBell($occupiedRatio, 0.14, 0.78, 0.13);
        $centerSignal = $this->smoothStep(0.055, 0.46, $centerDensity);
        $lowerSignal = $this->smoothStep(0.045, 0.43, $lowerCenterDensity);
        $continuitySignal = $this->smoothStep(0.28, 0.90, $verticalContinuity);
        $densityVariationSignal = $this->smoothStep(0.035, 0.25, $std);
        $borderControl = 1.0 - 0.45 * $this->smoothStep(0.58, 0.92, $borderSkinShare);

        $topologySignal = min(1.0, max(0.0,
            (0.26 * $centerSignal
            + 0.22 * $lowerSignal
            + 0.22 * $continuitySignal
            + 0.18 * $structuredOccupancy
            + 0.12 * $densityVariationSignal) * $borderControl
        ));

        $uniformGridSurfaceScore = min(1.0,
            $this->smoothStep(0.58, 0.92, $occupiedRatio)
            * (1.0 - $this->smoothStep(0.025, 0.16, $std))
            * $this->smoothStep(0.42, 0.88, $borderSkinShare)
        );

        return [
            'occupied_cell_ratio' => $occupiedRatio,
            'dense_cell_ratio' => $denseRatio,
            'vertical_continuity' => $verticalContinuity,
            'center_density' => $centerDensity,
            'lower_center_density' => $lowerCenterDensity,
            'border_skin_share' => $borderSkinShare,
            'cell_density_std' => $std,
            'topology_signal' => $topologySignal,
            'uniform_grid_surface_score' => $uniformGridSurfaceScore,
        ];
    }

    private function pairedShapeFeatures(array $components): array
    {
        $chestPair = $this->bilateralRegionScore(0.24, 0.64, 0.12, 0.88);
        $lowerPair = $this->bilateralRegionScore(0.53, 0.92, 0.10, 0.90);
        $centralLower = $this->regionDensity(0.34, 0.66, 0.55, 0.92);

        $pairFromComponents = 0.0;
        $interaction = 0.0;
        $limit = min(12, count($components));
        for ($i = 0; $i < $limit; $i++) {
            for ($j = $i + 1; $j < $limit; $j++) {
                $a = $components[$i];
                $b = $components[$j];
                $areaRatio = min($a['area'], $b['area']) / max(1, max($a['area'], $b['area']));
                $verticalDistance = abs($a['centroid_y'] - $b['centroid_y']) / $this->h;
                $horizontalDistance = abs($a['centroid_x'] - $b['centroid_x']) / $this->w;
                $sameBand = exp(-0.5 * ($verticalDistance / 0.12) ** 2);
                $usefulSeparation = $this->rangeBell($horizontalDistance, 0.12, 0.62, 0.10);
                $pair = $areaRatio * $sameBand * $usefulSeparation;
                $pairFromComponents = max($pairFromComponents, $pair);

                $bboxOverlapX = $this->intervalOverlap($a['min_x'], $a['max_x'], $b['min_x'], $b['max_x']) / max(1, min($a['width'], $b['width']));
                $bboxOverlapY = $this->intervalOverlap($a['min_y'], $a['max_y'], $b['min_y'], $b['max_y']) / max(1, min($a['height'], $b['height']));
                $near = exp(-0.5 * (($verticalDistance + $horizontalDistance) / 0.34) ** 2);
                $interaction = max($interaction, min(1.0, 0.45 * $bboxOverlapX + 0.35 * $bboxOverlapY + 0.20 * $near));
            }
        }

        return [
            'chest_pair' => min(1.0, 0.72 * $chestPair + 0.28 * $pairFromComponents),
            'lower_pair' => min(1.0, 0.76 * $lowerPair + 0.24 * $pairFromComponents),
            'central_lower_focus' => $this->smoothStep(0.05, 0.48, $centralLower),
            'multi_cluster_interaction' => $interaction,
        ];
    }

    private function bilateralRegionScore(float $y0f, float $y1f, float $x0f, float $x1f): float
    {
        $x0 = (int)round($this->w * $x0f);
        $x1 = (int)round($this->w * $x1f);
        $y0 = (int)round($this->h * $y0f);
        $y1 = (int)round($this->h * $y1f);
        $mid = intdiv($x0 + $x1, 2);

        $left = 0;
        $right = 0;
        $leftMoment = 0.0;
        $rightMoment = 0.0;
        for ($y = $y0; $y < $y1; $y++) {
            $row = $y * $this->w;
            for ($x = $x0; $x < $x1; $x++) {
                if ($this->mask[$row + $x] === 0) {
                    continue;
                }
                if ($x < $mid) {
                    $left++;
                    $leftMoment += ($mid - $x);
                } else {
                    $right++;
                    $rightMoment += ($x - $mid);
                }
            }
        }

        $halfArea = max(1, ($mid - $x0) * ($y1 - $y0));
        $leftDensity = $left / $halfArea;
        $rightDensity = $right / $halfArea;
        $balance = min($leftDensity, $rightDensity) / max(0.0001, max($leftDensity, $rightDensity));
        $density = $this->smoothStep(0.035, 0.52, ($leftDensity + $rightDensity) / 2.0);
        $spreadBalance = min($leftMoment, $rightMoment) / max(1.0, max($leftMoment, $rightMoment));

        return min(1.0, 0.52 * $balance + 0.33 * $density + 0.15 * $spreadBalance) * $density;
    }

    private function regionDensity(float $x0f, float $x1f, float $y0f, float $y1f): float
    {
        $x0 = (int)round($this->w * $x0f);
        $x1 = (int)round($this->w * $x1f);
        $y0 = (int)round($this->h * $y0f);
        $y1 = (int)round($this->h * $y1f);
        $count = 0;
        for ($y = $y0; $y < $y1; $y++) {
            $row = $y * $this->w;
            for ($x = $x0; $x < $x1; $x++) {
                $count += $this->mask[$row + $x];
            }
        }
        return $count / max(1, ($x1 - $x0) * ($y1 - $y0));
    }

    private function edgeAndTextureFeatures(): array
    {
        $skinEdges = 0;
        $skinComparisons = 0;
        $skinLumaSum = 0.0;
        $skinLumaSq = 0.0;
        $skinCount = 0;

        for ($y = 0; $y < $this->h; $y++) {
            for ($x = 0; $x < $this->w; $x++) {
                $idx = $y * $this->w + $x;
                if ($this->mask[$idx] === 0) {
                    continue;
                }

                $value = $this->luma[$idx];
                $skinLumaSum += $value;
                $skinLumaSq += $value * $value;
                $skinCount++;

                if ($x + 1 < $this->w && $this->mask[$idx + 1] === 1) {
                    $skinComparisons++;
                    if (abs($value - $this->luma[$idx + 1]) > 16.0) {
                        $skinEdges++;
                    }
                }
                if ($y + 1 < $this->h && $this->mask[$idx + $this->w] === 1) {
                    $skinComparisons++;
                    if (abs($value - $this->luma[$idx + $this->w]) > 16.0) {
                        $skinEdges++;
                    }
                }
            }
        }

        $mean = $skinLumaSum / max(1, $skinCount);
        $std = sqrt(max(0.0, $skinLumaSq / max(1, $skinCount) - $mean * $mean));

        return [
            'skin_edge_density' => min(1.0, ($skinEdges / max(1, $skinComparisons)) / 0.28),
            'skin_luma_variation' => min(1.0, $std / 58.0),
        ];
    }

    private function neutralSurfaceFeatures(): array
    {
        $whiteTotal = 0;
        $neutralTotal = 0;
        $whiteTorso = 0;
        $neutralTorso = 0;
        $torsoArea = 0;
        $whiteMiddleLower = 0;
        $middleLowerArea = 0;
        $rowContinuity = 0;
        $currentRows = 0;

        $torsoX0 = (int)floor($this->w * 0.16);
        $torsoX1 = (int)ceil($this->w * 0.84);
        $torsoY0 = (int)floor($this->h * 0.18);
        $torsoY1 = (int)ceil($this->h * 0.96);
        $middleY0 = (int)floor($this->h * 0.34);

        for ($y = 0; $y < $this->h; $y++) {
            $rowWhite = 0;
            $rowArea = 0;
            $row = $y * $this->w;
            for ($x = 0; $x < $this->w; $x++) {
                $idx = $row + $x;
                $isWhite = $this->whiteMask[$idx] ?? 0;
                $isNeutral = $this->neutralMask[$idx] ?? 0;
                $whiteTotal += $isWhite;
                $neutralTotal += $isNeutral;

                if ($x >= $torsoX0 && $x < $torsoX1 && $y >= $torsoY0 && $y < $torsoY1) {
                    $torsoArea++;
                    $whiteTorso += $isWhite;
                    $neutralTorso += $isNeutral;
                    $rowWhite += $isWhite;
                    $rowArea++;
                }
                if ($x >= $torsoX0 && $x < $torsoX1 && $y >= $middleY0 && $y < $torsoY1) {
                    $middleLowerArea++;
                    $whiteMiddleLower += $isWhite;
                }
            }

            if ($rowArea > 0 && ($rowWhite / $rowArea) >= 0.28) {
                $currentRows++;
                $rowContinuity = max($rowContinuity, $currentRows);
            } else {
                $currentRows = 0;
            }
        }

        return [
            'white_ratio' => $whiteTotal / max(1, $this->n),
            'neutral_ratio' => $neutralTotal / max(1, $this->n),
            'white_torso_ratio' => $whiteTorso / max(1, $torsoArea),
            'neutral_torso_ratio' => $neutralTorso / max(1, $torsoArea),
            'white_middle_lower_ratio' => $whiteMiddleLower / max(1, $middleLowerArea),
            'white_row_continuity' => min(1.0, $rowContinuity / max(1, $torsoY1 - $torsoY0)),
        ];
    }

    private function falsePositivePenalties(
        float $skinRatio,
        float $softSkinRatio,
        float $largestRatio,
        int $substantialCount,
        array $regions,
        float $lumaStd,
        float $chromaStd,
        float $entropy,
        array $edge,
        array $topology,
        array $neutral,
        float $whiteGarmentSignal,
        float $achromaticDominanceSignal,
        float $chromaticSkinRatio
    ): array {
        $tinySkin = $skinRatio < 0.045 ? 0.28 : ($skinRatio < 0.085 ? 0.13 : 0.0);

        $portraitLike = 0.0;
        if (
            $skinRatio < 0.29
            && $regions['upper_share_of_skin'] > 0.56
            && $regions['lower_ratio'] < 0.075
            && $substantialCount <= 2
        ) {
            $portraitLike = 0.13;
        }

        $uniformWarmSurface = 0.0;
        if (
            $softSkinRatio > 0.52
            && $largestRatio > 0.42
            && $lumaStd < 22.0
            && $chromaStd < 17.0
            && $entropy < 0.46
            && $edge['skin_edge_density'] < 0.28
        ) {
            $uniformWarmSurface = 0.22;
        }

        $fragmentedNoise = ($substantialCount === 0 && $skinRatio < 0.18) ? 0.085 : 0.0;

        $topOnly = 0.0;
        if ($regions['upper_share_of_skin'] > 0.72 && $regions['middle_ratio'] < 0.16 && $regions['lower_ratio'] < 0.045) {
            $topOnly = 0.12;
        }

        $lowDetailColorField = ($softSkinRatio > 0.65 && $entropy < 0.32 && $lumaStd < 14.0) ? 0.17 : 0.0;
        $uniformGridSurface = 0.14 * (float)($topology['uniform_grid_surface_score'] ?? 0.0);

        $borderFlood = 0.0;
        if (
            (float)($topology['border_skin_share'] ?? 0.0) > 0.78
            && (float)($topology['cell_density_std'] ?? 1.0) < 0.095
            && $entropy < 0.52
        ) {
            $borderFlood = 0.09;
        }

        $whiteGarment = 0.36 * $whiteGarmentSignal;
        $achromaticDominance = 0.28 * $achromaticDominanceSignal;

        $whitePortrait = 0.0;
        if (
            (float)($neutral['white_torso_ratio'] ?? 0.0) > 0.30
            && $regions['upper_share_of_skin'] > 0.48
            && $regions['lower_ratio'] < 0.11
            && $skinRatio < 0.24
        ) {
            $whitePortrait = 0.24;
        }

        $weakChromaticEvidence = 0.0;
        if ($skinRatio > 0.06 && $chromaticSkinRatio < min(0.045, $skinRatio * 0.42)) {
            $weakChromaticEvidence = 0.19;
        }

        $total = min(
            0.76,
            $tinySkin
            + $portraitLike
            + $uniformWarmSurface
            + $fragmentedNoise
            + $topOnly
            + $lowDetailColorField
            + $uniformGridSurface
            + $borderFlood
            + $whiteGarment
            + $achromaticDominance
            + $whitePortrait
            + $weakChromaticEvidence
        );

        return [
            'tiny_skin' => $tinySkin,
            'portrait_like_distribution' => $portraitLike,
            'uniform_warm_surface' => $uniformWarmSurface,
            'fragmented_noise' => $fragmentedNoise,
            'top_only_distribution' => $topOnly,
            'low_detail_color_field' => $lowDetailColorField,
            'uniform_grid_surface' => $uniformGridSurface,
            'border_flood' => $borderFlood,
            'white_garment' => $whiteGarment,
            'achromatic_dominance' => $achromaticDominance,
            'white_portrait_distribution' => $whitePortrait,
            'weak_chromatic_skin_evidence' => $weakChromaticEvidence,
            'total' => $total,
        ];
    }

    private function entropy(array $histogram, int $total): float
    {
        $entropy = 0.0;
        foreach ($histogram as $count) {
            if ($count <= 0) {
                continue;
            }
            $p = $count / max(1, $total);
            $entropy -= $p * log($p, 2);
        }
        return $entropy;
    }

    private function differenceHash(PixelGrid $grid): string
    {
        $hex = '';
        $nibble = 0;
        $bits = 0;
        for ($y = 0; $y < 8; $y++) {
            $sourceY = min($grid->height - 1, (int)floor(($y + 0.5) * $grid->height / 8.0));
            for ($x = 0; $x < 8; $x++) {
                $x1 = min($grid->width - 1, (int)floor(($x + 0.25) * $grid->width / 9.0));
                $x2 = min($grid->width - 1, (int)floor(($x + 1.25) * $grid->width / 9.0));
                $i1 = $sourceY * $grid->width + $x1;
                $i2 = $sourceY * $grid->width + $x2;
                $l1 = 0.299 * $grid->r[$i1] + 0.587 * $grid->g[$i1] + 0.114 * $grid->b[$i1];
                $l2 = 0.299 * $grid->r[$i2] + 0.587 * $grid->g[$i2] + 0.114 * $grid->b[$i2];
                $nibble = ($nibble << 1) | ($l1 > $l2 ? 1 : 0);
                $bits++;
                if ($bits === 4) {
                    $hex .= dechex($nibble);
                    $nibble = 0;
                    $bits = 0;
                }
            }
        }
        return str_pad($hex, 16, '0', STR_PAD_LEFT);
    }

    private function makeFeatureToken(array $features): string
    {
        $parts = [
            (int)round($features['skin_ratio'] * 20),
            (int)round($features['largest_component_ratio'] * 20),
            min(9, (int)$features['substantial_component_count']),
            (int)round($features['center_skin_ratio'] * 15),
            (int)round($features['lower_skin_ratio'] * 15),
            (int)round($features['chest_pair_signal'] * 10),
            (int)round($features['lower_pair_signal'] * 10),
            (int)round($features['occupied_grid_ratio'] * 10),
            (int)round($features['vertical_grid_continuity'] * 10),
            (int)round($features['color_entropy'] * 10),
            (int)round(($features['white_garment_signal'] ?? 0.0) * 10),
            (int)round(($features['chromatic_skin_ratio'] ?? 0.0) * 20),
        ];
        return implode('-', $parts);
    }

    private function confidenceLabel(float $score, float $threshold): string
    {
        $distance = abs($score - $threshold);
        if ($distance >= 0.22) {
            return 'very_high';
        }
        if ($distance >= 0.13) {
            return 'high';
        }
        if ($distance >= 0.065) {
            return 'medium';
        }
        return 'low';
    }

    private function smoothStep(float $edge0, float $edge1, float $x): float
    {
        if ($edge0 === $edge1) {
            return $x >= $edge1 ? 1.0 : 0.0;
        }
        $t = max(0.0, min(1.0, ($x - $edge0) / ($edge1 - $edge0)));
        return $t * $t * (3.0 - 2.0 * $t);
    }

    private function intervalOverlap(int $a0, int $a1, int $b0, int $b1): int
    {
        return max(0, min($a1, $b1) - max($a0, $b0) + 1);
    }
}

final class TokenMemory
{
    private string $path;
    private AppConfig $config;
    private array $data;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
        $file = (string)$config->get('tokens.file', 'tokens.json');
        $this->path = $config->resolvePath($file);
        $tokensExist = is_file($this->path);
        $existing = AtomicJsonFile::read($this->path);

        if ($tokensExist && $existing === null) {
            JsonResponse::error(
                'TOKENS_INVALID',
                'tokens.json exists but does not contain valid JSON.',
                500
            );
        }

        if ($existing === null) {
            $this->data = self::defaults();
            if (!AtomicJsonFile::write($this->path, $this->data)) {
                JsonResponse::error(
                    'TOKENS_CREATE_FAILED',
                    'tokens.json could not be created. Make sure the configured directory is writable.',
                    500
                );
            }
        } else {
            ModelIdentity::assertDocument($existing, 'tokens.json');
            $versionMatches = (int)($existing['version'] ?? 0) === ModelIdentity::ENGINE_VERSION;
            $revisionMatches = (string)($existing['analyzer_revision'] ?? '') === ModelIdentity::ANALYZER_REVISION;
            if (!$versionMatches || !$revisionMatches) {
                 
                $this->data = self::defaults();
                AtomicJsonFile::write($this->path, $this->data);
            } else {
                $this->data = array_replace_recursive(self::defaults(), $existing);
            }
        }

        ModelIdentity::assertDocument($this->data, 'tokens.json');
    }

    public static function defaults(): array
    {
        return [
            'version' => ModelIdentity::ENGINE_VERSION,
            'analyzer_revision' => ModelIdentity::ANALYZER_REVISION,
            'model_name' => ModelIdentity::NAME,
            'model_name_sha256' => ModelIdentity::NAME_SHA256,
            'description' => 'Bounded local image fingerprints, feature tokens, and rolling moderation statistics.',
            'updated_at' => gmdate('c'),
            'statistics' => [
                'total_analyzed' => 0,
                'explicit' => 0,
                'safe' => 0,
                'exact_cache_hits' => 0,
                'similarity_matches' => 0,
            ],
            'pattern_counts' => [],
            'entries' => [],
        ];
    }

    public function exact(string $sha256): ?array
    {
        if (!(bool)$this->config->get('tokens.enabled', true)) {
            return null;
        }

        foreach ((array)($this->data['entries'] ?? []) as $entry) {
            if (
                is_array($entry)
                && (int)($entry['engine_version'] ?? 0) === ModelIdentity::ENGINE_VERSION
                && hash_equals((string)($entry['sha256'] ?? ''), $sha256)
            ) {
                $this->data['statistics']['exact_cache_hits'] = (int)($this->data['statistics']['exact_cache_hits'] ?? 0) + 1;
                $this->persist();
                return $entry;
            }
        }
        return null;
    }

    public function similarityAdjustment(string $dhash): array
    {
        if (
            !(bool)$this->config->get('tokens.enabled', true) ||
            !(bool)$this->config->get('tokens.similarity_enabled', true)
        ) {
            return ['adjustment' => 0.0, 'distance' => null, 'matched' => false];
        }

        $maxDistance = (int)$this->config->get('tokens.max_hamming_distance', 5);
        $maxAdjustment = (float)$this->config->get('tokens.max_score_adjustment', 0.025);
        $highOnly = (bool)$this->config->get('tokens.only_use_high_confidence_neighbors', true);
        $best = null;
        $bestDistance = 999;

        foreach ((array)($this->data['entries'] ?? []) as $entry) {
            if (
                !is_array($entry)
                || !isset($entry['dhash'])
                || (int)($entry['engine_version'] ?? 0) !== ModelIdentity::ENGINE_VERSION
            ) {
                continue;
            }
            if ($highOnly && !in_array((string)($entry['confidence'] ?? ''), ['high', 'very_high'], true)) {
                continue;
            }
            $distance = self::hexHammingDistance($dhash, (string)$entry['dhash']);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $entry;
            }
        }

        if ($best === null || $bestDistance > $maxDistance) {
            return ['adjustment' => 0.0, 'distance' => null, 'matched' => false];
        }

        $strength = 1.0 - ($bestDistance / max(1.0, $maxDistance + 1.0));
        $direction = (bool)($best['explicit'] ?? false) ? 1.0 : -1.0;
        $adjustment = $direction * $maxAdjustment * $strength;
        $this->data['statistics']['similarity_matches'] = (int)($this->data['statistics']['similarity_matches'] ?? 0) + 1;

        return [
            'adjustment' => $adjustment,
            'distance' => $bestDistance,
            'matched' => true,
            'neighbor_explicit' => (bool)($best['explicit'] ?? false),
        ];
    }

    public function record(string $sha256, AnalysisResult $result): void
    {
        if (!(bool)$this->config->get('tokens.enabled', true)) {
            return;
        }

        $entries = [];
        foreach ((array)($this->data['entries'] ?? []) as $entry) {
            if (is_array($entry) && (string)($entry['sha256'] ?? '') !== $sha256) {
                $entries[] = $entry;
            }
        }

        array_unshift($entries, [
            'engine_version' => ModelIdentity::ENGINE_VERSION,
            'analyzer_revision' => ModelIdentity::ANALYZER_REVISION,
            'sha256' => $sha256,
            'dhash' => $result->dhash,
            'feature_token' => $result->featureToken,
            'explicit' => $result->explicit,
            'score' => round($result->score, 6),
            'confidence' => $result->confidence,
            'features' => [
                'skin_ratio' => round((float)$result->features['skin_ratio'], 5),
                'largest_component_ratio' => round((float)$result->features['largest_component_ratio'], 5),
                'component_count' => (int)$result->features['component_count'],
                'center_skin_ratio' => round((float)$result->features['center_skin_ratio'], 5),
                'lower_skin_ratio' => round((float)$result->features['lower_skin_ratio'], 5),
                'occupied_grid_ratio' => round((float)$result->features['occupied_grid_ratio'], 5),
                'vertical_grid_continuity' => round((float)$result->features['vertical_grid_continuity'], 5),
                'border_skin_share' => round((float)$result->features['border_skin_share'], 5),
                'chromatic_skin_ratio' => round((float)($result->features['chromatic_skin_ratio'] ?? 0.0), 5),
                'white_garment_signal' => round((float)($result->features['white_garment_signal'] ?? 0.0), 5),
                'decision_gate_passed' => (bool)($result->features['decision_gate_passed'] ?? false),
            ],
            'seen_at' => gmdate('c'),
        ]);

        $maxEntries = (int)$this->config->get('tokens.max_entries', 750);
        $this->data['entries'] = array_slice($entries, 0, $maxEntries);
        $this->data['statistics']['total_analyzed'] = (int)($this->data['statistics']['total_analyzed'] ?? 0) + 1;
        if ($result->explicit) {
            $this->data['statistics']['explicit'] = (int)($this->data['statistics']['explicit'] ?? 0) + 1;
        } else {
            $this->data['statistics']['safe'] = (int)($this->data['statistics']['safe'] ?? 0) + 1;
        }

        $token = $result->featureToken;
        $this->data['pattern_counts'][$token] = (int)($this->data['pattern_counts'][$token] ?? 0) + 1;
        if (count($this->data['pattern_counts']) > 1000) {
            arsort($this->data['pattern_counts']);
            $this->data['pattern_counts'] = array_slice($this->data['pattern_counts'], 0, 750, true);
        }

        $this->persist();
    }

    private function persist(): void
    {
        $this->data['updated_at'] = gmdate('c');
        AtomicJsonFile::write($this->path, $this->data);
    }

    private static function hexHammingDistance(string $a, string $b): int
    {
        $a = strtolower(str_pad(substr($a, 0, 16), 16, '0'));
        $b = strtolower(str_pad(substr($b, 0, 16), 16, '0'));
        static $bitCount = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];
        $distance = 0;
        for ($i = 0; $i < 16; $i++) {
            $x = hexdec($a[$i]) ^ hexdec($b[$i]);
            $distance += $bitCount[$x];
        }
        return $distance;
    }
}

final class ModerationApplication
{
    public static function run(): never
    {
        $started = microtime(true);
        $config = AppConfig::load();
        $tokenMemory = new TokenMemory($config);
        $mode = self::resolveMode($config);
        $sourceParameter = (string)$config->get('endpoint.source_parameter', 'url');
        $url = isset($_GET[$sourceParameter]) ? trim((string)$_GET[$sourceParameter]) : '';

        if ($url === '') {
            JsonResponse::error(
                'MISSING_IMAGE_URL',
                'An image URL is required in the configured GET parameter.',
                400,
                ['parameter' => $sourceParameter]
            );
        }

        $blob = UrlImageDownloader::download($url, $config);
        $sha256 = hash('sha256', $blob);
        $image = RasterImage::fromBlob($blob, $config);

        $cached = $tokenMemory->exact($sha256);
        $cacheHit = false;
        if (
            $cached !== null &&
            (int)($cached['engine_version'] ?? 0) === ModelIdentity::ENGINE_VERSION &&
            isset($cached['score'], $cached['explicit'], $cached['confidence'], $cached['dhash'], $cached['feature_token']) &&
            isset(
                $cached['features']['occupied_grid_ratio'],
                $cached['features']['vertical_grid_continuity'],
                $cached['features']['decision_gate_passed'],
                $cached['features']['white_garment_signal']
            )
        ) {
            $cacheHit = true;
            $result = new AnalysisResult(
                (float)$cached['score'],
                (bool)$cached['explicit'],
                (string)$cached['confidence'],
                is_array($cached['features'] ?? null) ? $cached['features'] : [],
                [],
                (string)$cached['dhash'],
                (string)$cached['feature_token']
            );
            $similarity = ['adjustment' => 0.0, 'distance' => 0, 'matched' => true, 'exact' => true];
        } else {
            $grid = $image->analysisGrid((int)$config->get('limits.analysis_max_side', 360));
            $analyzer = new ExplicitImageAnalyzer($config);
            $result = $analyzer->analyze($grid);

            $similarity = $tokenMemory->similarityAdjustment($result->dhash);
            $result->score = max(0.0, min(1.0, $result->score + (float)$similarity['adjustment']));
            $threshold = (float)$config->get('analysis.explicit_threshold', 0.665);
            $decisionGatePassed = (bool)($result->features['decision_gate_passed'] ?? false);
            $result->explicit = $result->score >= $threshold && $decisionGatePassed;
            if (!$result->explicit && $result->score >= $threshold) {
                $result->score = max(0.0, $threshold - 0.001);
            }
            $result->confidence = self::confidenceLabel($result->score, $threshold);
            $tokenMemory->record($sha256, $result);
        }

        $payload = [
            'success' => true,
            'model' => [
                'name' => ModelIdentity::NAME,
                'name_sha256' => ModelIdentity::NAME_SHA256,
                'engine_version' => ModelIdentity::ENGINE_VERSION,
                'analyzer_revision' => ModelIdentity::ANALYZER_REVISION,
            ],
            'mode' => $mode,
            'explicit' => $result->explicit,
            'status' => $result->explicit ? 'explicit_content_detected' : 'no_explicit_content_detected',
            'message' => $result->explicit
                ? 'The image appears to contain explicit or highly sexualized visual content.'
                : 'No explicit content was detected by the configured local moderation engine.',
            'score' => round($result->score, 6),
            'threshold' => round((float)$config->get('analysis.explicit_threshold', 0.665), 6),
            'confidence' => $result->confidence,
            'engine' => $image->engine(),
            'cache_hit' => $cacheHit,
            'image' => [
                'width' => $image->width(),
                'height' => $image->height(),
                'sha256' => $sha256,
            ],
            'memory' => [
                'similarity_match' => (bool)($similarity['matched'] ?? false),
                'hamming_distance' => $similarity['distance'] ?? null,
                'score_adjustment' => round((float)($similarity['adjustment'] ?? 0.0), 6),
                'feature_token' => $result->featureToken,
            ],
        ];

        if ($mode === 2) {
            $saved = self::saveProcessedImage($image, $result->explicit, $sha256, $config);
            $payload['processing'] = [
                'saved' => true,
                'blurred' => $result->explicit,
                'action' => $result->explicit ? 'saved_with_full_image_blur' : 'saved_without_blur',
                'filename' => $saved['filename'],
                'relative_path' => $saved['relative_path'],
                'absolute_path' => $saved['absolute_path'],
            ];
        }

        $debugRequested = isset($_GET['debug']) && in_array(strtolower((string)$_GET['debug']), ['1', 'true', 'yes'], true);
        if ($debugRequested && (bool)$config->get('endpoint.allow_debug_output', true)) {
            $payload['analysis'] = [
                'features' => self::roundRecursive($result->features),
                'signals' => self::roundRecursive($result->signals),
            ];
        }

        $payload['processing_time_ms'] = round((microtime(true) - $started) * 1000.0, 2);
        JsonResponse::send($payload);
    }

    private static function resolveMode(AppConfig $config): int
    {
        $mode = (int)$config->get('mode', 1);
        if ((bool)$config->get('endpoint.allow_get_mode_override', true) && isset($_GET['mode'])) {
            $mode = (int)$_GET['mode'];
        }

        if (!in_array($mode, [1, 2], true)) {
            JsonResponse::error('INVALID_MODE', 'Mode must be either 1 or 2.');
        }
        return $mode;
    }

    private static function saveProcessedImage(RasterImage $image, bool $explicit, string $sha256, AppConfig $config): array
    {
        $storageConfig = (string)$config->get('storage.directory', 'storage/moderated');
        $directory = $config->resolvePath($storageConfig);
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$config->get('storage.filename_prefix', 'moderated_'));
        $prefix = $prefix !== '' ? $prefix : 'moderated_';
        $filename = $prefix . gmdate('Ymd_His') . '_' . substr($sha256, 0, 16) . '_' . bin2hex(random_bytes(3)) . '.jpg';
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;

        $image->save($absolutePath, $explicit, $config);

        $publicPrefix = trim((string)$config->get('storage.public_prefix', 'storage/moderated'), '/\\');
        $relative = $publicPrefix === '' ? $filename : $publicPrefix . '/' . $filename;

        return [
            'filename' => $filename,
            'relative_path' => str_replace('\\', '/', $relative),
            'absolute_path' => $absolutePath,
        ];
    }

    private static function confidenceLabel(float $score, float $threshold): string
    {
        $distance = abs($score - $threshold);
        if ($distance >= 0.22) return 'very_high';
        if ($distance >= 0.13) return 'high';
        if ($distance >= 0.065) return 'medium';
        return 'low';
    }

    private static function roundRecursive(mixed $value): mixed
    {
        if (is_float($value)) {
            return round($value, 6);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::roundRecursive($item);
            }
        }
        return $value;
    }
}

try {
    ModerationApplication::run();
} catch (Throwable $e) {
    error_log('Image moderation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    JsonResponse::error(
        'INTERNAL_SERVER_ERROR',
        'The image could not be processed because of an internal server error.',
        500
    );
}
