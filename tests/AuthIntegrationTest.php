<?php

use PHPUnit\Framework\TestCase;

class AuthIntegrationTest extends TestCase
{
    public function testLoginWithValidCredentials()
    {
        // Simula una petición HTTP POST al endpoint de login
        $url = 'http://localhost:8000/login';
        $data = [
            'username' => 'admin',
            'password' => 'admin123'
        ];
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $this->assertNotFalse($result, 'No se pudo conectar al backend');
        $json = json_decode($result, true);
        $this->assertArrayHasKey('token', $json);
        $this->assertArrayHasKey('user', $json);
        $this->assertEquals('admin', $json['user']['username']);
    }

    public function testLoginWithInvalidCredentials()
    {
        $url = 'http://localhost:8000/login';
        $data = [
            'username' => 'admin',
            'password' => 'incorrecta'
        ];
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true // Permite capturar respuestas 401
            ],
        ];
        $context  = stream_context_create($options);
        $handle = fopen($url, 'r', false, $context);
        $this->assertNotFalse($handle, 'No se pudo conectar al backend');
        $result = stream_get_contents($handle);
        $meta = stream_get_meta_data($handle);
        fclose($handle);

        $statusLine = $meta['wrapper_data'][0] ?? '';
        $this->assertStringContainsString('401', $statusLine, 'Se esperaba un código 401 para credenciales incorrectas');
        $json = json_decode($result, true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('Contraseña incorrecta', $json['error']);
    }
}
