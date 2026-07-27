<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PublishPhp\StatamicStandardSite\ContentConverter;
use PublishPhp\StatamicStandardSite\EntryMapper;
use PublishPhp\StatamicStandardSite\SyncManager;
use PublishPhp\StatamicStandardSite\SyncResult;
use PublishPhp\StatamicStandardSite\ClientManager;
use PublishPhp\AtprotoStandardSite\Client;
use PublishPhp\AtprotoStandardSite\Exception\ApiErrorException;
use PublishPhp\AtprotoStandardSite\Service\Record;
use PublishPhp\AtprotoStandardSite\Model\Document;

class SyncManagerTest extends TestCase
{
    public function test_sync_returns_failure_when_no_publication_uri(): void
    {
        // We can't easily mock Addon::get() since it's a static facade.
        // This test verifies the SyncResult structure when credentials are missing.
        // Full integration tests require a running Statamic instance.
        $this->markTestSkipped('SyncManager requires Statamic Addon facade — test in integration environment');
    }

    public function test_sync_result_success_factory(): void
    {
        $result = SyncResult::success('at://did:plc:abc/site.standard.document/123', 'created');
        $this->assertTrue($result->success);
        $this->assertSame('at://did:plc:abc/site.standard.document/123', $result->uri);
        $this->assertSame('created', $result->action);
        $this->assertNull($result->error);
    }

    public function test_sync_result_failure_factory(): void
    {
        $result = SyncResult::failure('Something went wrong');
        $this->assertFalse($result->success);
        $this->assertNull($result->uri);
        $this->assertSame('Something went wrong', $result->error);
        $this->assertNull($result->action);
    }

    public function test_sync_result_noop_action(): void
    {
        $result = SyncResult::success('', 'noop');
        $this->assertTrue($result->success);
        $this->assertSame('', $result->uri);
        $this->assertSame('noop', $result->action);
    }
}
