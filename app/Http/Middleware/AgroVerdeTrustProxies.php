<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * AgroVerdeTrustProxies
 *
 * Declara quais proxies o Laravel deve acreditar ao ler os headers
 * X-Forwarded-*.
 *
 * ── Por que existe ─────────────────────────────────────────────────────────
 * O acesso público não chega direto no container: entra pelo nginx compartilhado
 * (container `wp-nginx`) via `vet.conf`, que seta `X-Forwarded-Proto: https`.
 * Sem confiar nesse proxy, o Laravel acha que a requisição é http e gera
 * redirects para `http://...`, o que no navegador vira loop contra o HTTPS.
 *
 * O core (`app/Http/Middleware/TrustProxies.php`) deixa `$proxies = null`, ou
 * seja, nenhum proxy é confiável. Em vez de editar esse arquivo do core — que
 * quebraria o merge com o upstream — o AgroverdeServiceProvider faz bind deste
 * middleware no lugar dele. Ver AGROVERDE.md, camada A do fork.
 *
 * ── Por que confiar em tudo ('*') ──────────────────────────────────────────
 * O app não publica nenhuma porta no host: o único caminho até ele é a rede
 * Docker `wordpress_wp-network`, compartilhada com o nginx. Não existe acesso
 * direto, então qualquer X-Forwarded-* que chegar veio do nosso proxy.
 */
class AgroVerdeTrustProxies extends TrustProxies
{
    /**
     * 'A rede Docker do nginx compartilhado é a única fonte possível.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
