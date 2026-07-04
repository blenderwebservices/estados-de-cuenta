# Proceso de Descarga de Archivos Excel

Este documento detalla el ciclo de vida de la exportación y descarga de archivos Excel (`.xlsx`) en el módulo de Estados de Cuenta, y analiza las razones por las que la descarga podría fallar en un entorno Livewire/Filament.

## Listado de Procesos para la Descarga

1. **Interacción del Usuario:** El usuario hace clic en el botón de acción "Excel" dentro de la tabla de Estados de Cuenta.
2. **Generación del Archivo:** La acción de Filament inyecta el servicio `ExcelExporter` y ejecuta el método `export($record)`.
3. **Guardado Temporal (Disco Duro):** El `ExcelExporter` utiliza `PhpSpreadsheet` para generar el documento en memoria y luego lo guarda físicamente usando la función nativa de PHP `tempnam(sys_get_temp_dir(), '...')`. Esto crea un archivo temporal en la carpeta temporal del sistema operativo (por ejemplo `/tmp` en Mac/Linux).
4. **Retorno de la Respuesta de Laravel:** La acción devuelve una respuesta binaria usando la instrucción `response()->download($filePath, $fileName)`.
5. **Intercepción por Livewire (AJAX):** Al estar en un entorno Filament v3, la petición original es una llamada AJAX de Livewire. Livewire intercepta la respuesta de descarga binaria de Laravel.
6. **Manejo Interno de Livewire:** Livewire, para poder entregar un archivo vía AJAX, mueve (o copia) el archivo especificado hacia su propio almacenamiento temporal, el cual está configurado usualmente en `storage/app/livewire-tmp/`.
7. **Generación de URL Firmada:** Livewire responde al navegador (vía JSON) con una instrucción ("effect") que contiene una **URL firmada temporal** y un identificador único (UUID).
8. **Descarga Final:** El JavaScript del navegador recibe esa URL firmada y hace una petición tradicional GET a dicha URL (`/livewire/download-file?signature=...`).
9. **Entrega del Archivo:** El controlador interno de Livewire recibe la petición, verifica la firma, busca el archivo en `livewire-tmp/`, y lo entrega al navegador forzando la descarga (attachment) con el `filename` original provisto.

## Análisis del Problema ("UUID y Error 404/500")

Actualmente el navegador termina descargando un archivo con un nombre tipo UUID (ej. `b4d4112e-5ff5...`) que carece de extensión `.xlsx` y que curiosamente pesa entre 10KB y 13KB.

Basados en el proceso anterior, esto sucede cuando el "Paso 9" falla. Específicamente:
* El archivo pesa 10-13KB porque ese es el peso exacto de una **página HTML de error de Laravel** (como un 404 o un error 500 de Ignition). No estás descargando el Excel, estás descargando la pantalla de error renderizada como si fuera un archivo.
* Al ser un error de Laravel, la respuesta HTTP carece del encabezado correcto `Content-Disposition: attachment; filename="archivo.xlsx"`.
* Ante la falta de este encabezado, el navegador opta por usar la última parte de la URL como nombre de archivo para guardar el contenido (que en el caso de Livewire es el UUID del identificador de descarga).

### ¿Por qué fallaría el Paso 9 (lectura desde livewire-tmp)?

1. **(Solucionado) Eliminación prematura:** Originalmente, se estaba usando `deleteFileAfterSend(true)` en el controlador original, lo cual borraba el archivo original de `/tmp/` antes de que el navegador pudiera hacer la petición secundaria. 
2. **Carpeta `livewire-tmp` Inexistente o Sin Permisos:** Livewire necesita escribir en `storage/app/livewire-tmp/`. Si esta carpeta no existe o tiene problemas de permisos (muy común en entornos locales o tras cambiar de rama/entorno), Livewire no puede crear el archivo para la descarga y el endpoint lanza un error 500 silencioso.
3. **Conflictos con Symlinks:** Si bien el endpoint de descarga de Livewire usa PHP nativo y no requiere acceso directo y público al archivo (como sería en `public/storage`), ciertas configuraciones de cache o personalizaciones en el `config/livewire.php` podrían estar enrutando mal la persistencia si el symlink de storage no está generado correctamente o apunta a una ruta inválida.

## Siguiente Acción

Validaremos si este error 500 se está originando por una incapacidad de escribir en la carpeta temporal de Laravel/Livewire, o si es preferible cambiar la estrategia para servir la descarga escribiendo el documento directamente sobre el *buffer de salida* del sistema (evitando los archivos temporales por completo), lo cual es 100% infalible en arquitecturas Livewire.
