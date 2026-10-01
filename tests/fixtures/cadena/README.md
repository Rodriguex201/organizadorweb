# Fixtures sintéticos de Cadena

Exclusivamente para pruebas. No son exportaciones reales y no deben cargarse para facturación.
NIT, emisor, identidades y cantidades son datos de prueba, resueltos contra un catálogo en memoria.

- `nota_debito_92.csv`: dos identidades diferentes y repetición de la primera.
- `nota_ajuste_95.csv`: una nota de ajuste, sin documento soporte 05.
- `aceptacion_tacita_034.csv`: siete aceptaciones tácitas, sin eventos 032.
- `eventos_cinco_codigos.csv`: cantidades distintas (1, 2, 3, 4, 5) para detectar cruces de categorías.

Ejecutar `php tests/Standalone/CadenaPositiveCodesTest.php`. Usa el lector CSV y los servicios reales, sin iniciar Laravel, cargar .env, conectarse a bases de datos ni escribir archivos. No depende de PHPUnit.

En Fase 2 los contadores viven en `cantidades[código]`, no en una fila persistida. Los nombres de negocio solicitados se corresponden con 92 → numero_nota_debito, 95 → numero_nota_ajuste, 05 → numero_documento_soporte y 032 → contador comercial de eventos. La prueba verifica esos significados sin introducir un adaptador de persistencia.
