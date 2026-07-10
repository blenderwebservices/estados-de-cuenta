# Sistema de Estados de Cuenta

## Definición
Esta aplicación es un sistema desarrollado en **Laravel** con **Filament** y **Livewire**, diseñado para la gestión, análisis y exportación de estados de cuenta. Su objetivo principal es procesar datos financieros, permitiendo a los usuarios visualizar, administrar e interactuar con la información mediante una interfaz de administración moderna y eficiente.

## Funcionalidades Principales
1. **Panel de Administración (Filament):** Interfaz robusta y amigable para la gestión integral de los registros de estados de cuenta.
2. **Procesamiento y Parsing de Datos:** Funcionalidades para analizar, normalizar y estructurar la información bancaria/financiera entrante.
3. **Exportación a Excel:** Generación eficiente de reportes y descargas en formato `.xlsx` usando `PhpSpreadsheet`, integrado de forma nativa con el ciclo de vida de Livewire.
4. **Renderizado de PDF mediante Python:** Integración con scripts de Python para la generación de PDFs de alta fidelidad. El renderizado y maquetación compleja de documentos se delega a un entorno especializado de Python.

## Despliegue en Servidor Ubuntu con Plesk

A continuación, se detalla una sugerencia de despliegue en un servidor Ubuntu administrado con Plesk. El objetivo es que la aplicación sea autosuficiente, pudiendo ejecutar tanto su núcleo en PHP como los scripts auxiliares en Python de manera segura y aislada.

### 1. Preparación del Entorno PHP (Laravel)
- **Dominio y Webroot:** Configura el dominio en Plesk, asegurando que la "Raíz del documento" (Document Root) apunte a la carpeta `public` del proyecto (ej: `/httpdocs/public`).
- **Versión de PHP:** Selecciona la versión de PHP requerida por Laravel (PHP 8.2 o superior) en la configuración de alojamiento de Plesk.
- **Base de Datos:** Crea la base de datos MySQL/MariaDB desde el panel de Plesk y actualiza el archivo `.env` del proyecto con estas credenciales.
- **Instalación de Dependencias PHP y Node:** Accede vía SSH a la carpeta raíz del proyecto y ejecuta:
  ```bash
  composer install --optimize-autoloader --no-dev
  npm install
  npm run build
  php artisan key:generate
  php artisan migrate --force
  php artisan storage:link
  ```

### 2. Preparación del Entorno Python (venv)
Para asegurar que las dependencias de Python (como librerías de generación de PDF o utilidades de análisis) no interfieran con el sistema operativo y la app sea portable, se debe usar un entorno virtual (`venv`) dentro de la misma estructura del proyecto.

1. **Creación Automática vía Composer:** 
   El archivo `composer.json` ha sido configurado para manejar esto de manera transparente. Al ejecutar comandos como `composer update` (o `composer run setup`), se disparará automáticamente el script integrado `setup-python`.

2. **Ejecución Manual del Entorno:**
   Si en algún momento necesitas forzar la instalación o actualización de las dependencias de Python (por ejemplo, después de modificar `requirements.txt`), simplemente ejecuta:
   ```bash
   composer run setup-python
   ```
   Este comando genérico (válido en Local y Ubuntu/Plesk) creará la carpeta `venv` si no existe, e instalará dependencias como `pdfplumber` sin necesidad de activar entornos manualmente en la terminal.

3. **Ejecución Autónoma desde Laravel:**
   Cuando Laravel necesite invocar el script de Python para renderizar el PDF, no debe usar el comando `python` global del servidor, sino el binario específico del entorno virtual local. 
   
   Ejemplo de cómo la aplicación orquesta internamente la ejecución desde PHP usando `Symfony\Component\Process\Process` (la configuración base ya lee de este directorio local `venv`):
   ```php
   use Symfony\Component\Process\Process;

   // Apuntamos al binario de Python DENTRO del venv local de la app
   $pythonBinary = config('services.python.path'); // Resuelve por defecto a base_path('venv/bin/python')
   
   // La ruta del script a ejecutar
   $scriptPath = base_path('database/scripts/parse_statement.py');
   
   // Ejecutamos el proceso
   $process = new Process([$pythonBinary, $scriptPath, $argumento1]);
   $process->run();

   if (!$process->isSuccessful()) {
       throw new \RuntimeException($process->getErrorOutput());
   }

   $output = $process->getOutput();
   ```
   De esta manera, la aplicación es totalmente autosuficiente, conteniendo sus propias dependencias de PHP en `vendor/` y de Python en `venv/`.

### 3. Permisos en Plesk
Asegúrate de que el usuario del sistema web de Plesk (usualmente la cuenta de sistema asignada a la suscripción) tenga permisos de ejecución y escritura sobre la carpeta del proyecto. Es vital que los directorios `storage/`, `bootstrap/cache/` y la carpeta `venv/` tengan los permisos correctos (generalmente 755 o 775) para que el servidor web pueda escribir archivos temporales y ejecutar el binario de Python.
