# DigitalOcean Spaces

DigitalOcean Spaces es almacenamiento de objetos compatible con Amazon S3. Este proyecto
lo usa mediante el disco privado `spaces` de Laravel; las evidencias no deben guardarse
como archivos públicos en el servidor de la API.

## 1. Crear el Space

1. En DigitalOcean, abrir **Spaces Object Storage** y crear un Space.
2. Elegir la región más cercana al backend, por ejemplo `nyc3`.
3. Elegir un nombre único para el bucket, por ejemplo `mis-vales-evidencias-prod`.
4. Mantener los archivos privados. No activar listado público ni usar el bucket para
   credenciales, documentos o evidencias sensibles.
5. Crear una **Spaces Access Key** en API > Spaces Keys. Guardar el Access Key y Secret
   Key en un gestor de secretos: el Secret sólo se muestra una vez.

## 2. Configurar el entorno

En el archivo `.env` del servidor, completar los valores sin comillas adicionales:

```dotenv
FILESYSTEM_DISK=local
DO_SPACES_KEY=TU_ACCESS_KEY
DO_SPACES_SECRET=TU_SECRET_KEY
DO_SPACES_REGION=nyc3
DO_SPACES_BUCKET=mis-vales-evidencias-prod
DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
DO_SPACES_URL=
```

Para una región distinta, sustituir `nyc3` en `DO_SPACES_REGION` y en el endpoint. No
subir `.env` al repositorio ni enviar las llaves por chat. Después de actualizar variables
en producción, ejecutar `php artisan config:clear` y reiniciar los workers o contenedores.

El adaptador S3 ya está instalado mediante `league/flysystem-aws-s3-v3`; no es necesario
agregar otra dependencia.

## 3. Probar la carga

Iniciar sesión con un usuario `administrator` o `general_manager`, obtener un token Sanctum
y enviar `multipart/form-data`:

```bash
curl -X POST "https://API_HOST/api/v1/system/storage/spaces/test-upload" \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Accept: application/json" \
  -F "file=@C:/ruta/evidence.png"
```

Se aceptan JPG, JPEG, PNG, WEBP y PDF de hasta 10 MB. La respuesta contiene el `path` del
objeto y un `temporary_url` firmado que expira en 10 minutos. Abrir esa URL confirma que el
objeto quedó guardado, sin volver público el bucket.

El endpoint es sólo para verificar infraestructura. Las cargas reales de INE, comprobantes,
fotos de verificación y archivos bancarios deben reutilizar el servicio
`SpacesStorageService`, pero guardarse en prefijos de negocio como `customers/`,
`applications/` o `bank-imports/`, con auditoría y controles de acceso propios.

## 4. Seguridad y operación

- Usar una llave exclusiva para este proyecto y rotarla ante cualquier exposición.
- Mantener los objetos privados y entregar acceso mediante URLs temporales.
- Configurar CORS en el Space sólo para los dominios del frontend cuando éste suba archivos
  directamente; el endpoint actual sube a través de Laravel y no lo requiere.
- Definir reglas de ciclo de vida y retención antes de almacenar evidencias productivas.
- En producción, registrar alertas de errores S3/Spaces, capacidad y costos de transferencia.