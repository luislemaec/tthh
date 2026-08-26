<?php
namespace App\Services;

class ActiveDirectoryService
{
    // true = credenciales válidas en AD. false = no está en AD o la clave no coincide
    // (nunca lanza excepción — el caller decide el fallback a clave local).
    public static function autenticar(string $identificacion, string $password): bool
    {
        $host = config('services.ad.host');
        if (empty($host) || !function_exists('ldap_connect')) {
            return false;
        }

        $conn = @ldap_connect($host, (int) config('services.ad.port', 389));
        if (!$conn) {
            return false;
        }

        try {
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

            if (config('services.ad.use_tls')) {
                if (!@ldap_start_tls($conn)) {
                    return false;
                }
            }

            // 1. Bind con la cuenta de servicio para poder buscar al usuario por su cédula
            $bindDn  = config('services.ad.bind_dn');
            $bindPwd = config('services.ad.bind_password');
            if (!@ldap_bind($conn, $bindDn, $bindPwd)) {
                return false;
            }

            $atributo = config('services.ad.employee_attr', 'employeeID');
            $filtro   = '(' . $atributo . '=' . self::escaparFiltro($identificacion) . ')';

            $resultado = @ldap_search($conn, config('services.ad.base_dn'), $filtro, ['dn']);
            if (!$resultado) {
                return false;
            }

            $entradas = ldap_get_entries($conn, $resultado);
            if (($entradas['count'] ?? 0) !== 1) {
                // 0 = no existe en AD, >1 = cédula duplicada en el directorio (ambiguo, no autenticar)
                return false;
            }

            $dnUsuario = $entradas[0]['dn'];

            // 2. El bind que realmente valida la clave: como el propio usuario, con su DN + password
            $bindUsuario = @ldap_bind($conn, $dnUsuario, $password);
            return (bool) $bindUsuario;
        } catch (\Throwable) {
            return false;
        } finally {
            @ldap_unbind($conn);
        }
    }

    private static function escaparFiltro(string $valor): string
    {
        return function_exists('ldap_escape') ? ldap_escape($valor, '', LDAP_ESCAPE_FILTER) : addslashes($valor);
    }
}
