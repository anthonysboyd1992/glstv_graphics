<?php

namespace App\Http\Controllers;

use App\Models\Show;
use App\Services\Shows\DataSourceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The endpoints vMix polls.
 *
 * "live" is a single row describing what is on air now. "rundown" is one row per
 * look in running order, which is what lets an operator jump straight to a cue
 * with DataSourceSelectRow instead of waiting for the next refresh.
 */
class DataSourceController extends Controller
{
    public function __construct(protected DataSourceBuilder $builder) {}

    public function liveJson(Request $request, string $uuid): JsonResponse
    {
        return $this->json($this->builder->rows($this->show($request, $uuid)));
    }

    public function liveXml(Request $request, string $uuid): Response
    {
        return $this->xml($this->builder->toXml($this->builder->rows($this->show($request, $uuid))));
    }

    public function rundownJson(Request $request, string $uuid): JsonResponse
    {
        return $this->json($this->builder->rundownRows($this->show($request, $uuid)));
    }

    public function rundownXml(Request $request, string $uuid): Response
    {
        return $this->xml($this->builder->toXml($this->builder->rundownRows($this->show($request, $uuid))));
    }

    protected function show(Request $request, string $uuid): Show
    {
        $show = Show::with(['sections', 'textDefaults.textKey.group'])
            ->where('uuid', $uuid)
            ->first();

        // A wrong token is reported the same way as a wrong id so the endpoint
        // cannot be used to confirm that a broadcast exists.
        if (! $show || ! hash_equals($show->token, (string) $request->query('token'))) {
            throw new NotFoundHttpException;
        }

        return $show;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    protected function json(array $rows): JsonResponse
    {
        return response()->json($rows)->withHeaders($this->freshnessHeaders());
    }

    protected function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ])->withHeaders($this->freshnessHeaders());
    }

    /**
     * @return array<string, string>
     */
    protected function freshnessHeaders(): array
    {
        // vMix must never serve a cached copy of this; the whole point is that
        // it reflects the board right now.
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
