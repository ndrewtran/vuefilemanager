<?php
namespace Domain\Settings\DTO;

class S3CredentialsData
{
    public string $key;
    public string $secret;
    public string $region;
    public string $bucket;
    public string $endpoint;

    public function __construct(array $data)
    {
        $this->key = $data['key'];
        $this->secret = $data['secret'];
        $this->region = $data['region'];
        $this->bucket = $data['bucket'];
        $this->endpoint = $data['endpoint'];
    }

    public static function fromRequest($request): self
    {
        return new self([
            'key'      => $request->input('storage.s3.key'),
            'secret'   => $request->input('storage.s3.secret'),
            'region'   => $request->input('storage.s3.region'),
            'bucket'   => $request->input('storage.s3.bucket'),
            'endpoint' => $request->input('storage.s3.endpoint'),
        ]);
    }
}
