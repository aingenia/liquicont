<?php
/**
 * views/ayuda/insumo.php
 * Contenido del modal de ayuda del módulo Insumos.
 * Se carga vía AJAX en el <dialog id="ayudaModal">.
 */
?>

<h3>¿Qué es un insumo?</h3>
<p>
    Un <strong>insumo</strong> es cualquier materia prima que se consume en la elaboración
    de los artículos: alcohol, esencias, colorantes, botellas, etiquetas, tapas, etc.
    Los artículos se arman a partir de una <strong>fórmula</strong> que combina insumos.
</p>

<h3>Cómo crear un insumo</h3>
<ol>
    <li>Completá el <strong>nombre</strong> (obligatorio) y la <strong>unidad de medida</strong>.</li>
    <li>En "Medida por unidad de stock", poné cuántas unidades base entran en 1 unidad de stock.
        Ejemplo: si el alcohol viene en bidones de 20 litros, unidad = <code>Litro</code> y medida = <code>20</code>.</li>
    <li>Si el insumo es una bebida alcohólica, tildá el checkbox correspondiente y
        definí la <strong>alícuota del impuesto interno</strong>.</li>
    <li>Guardá. Vas a poder agregar proveedores y sus costos en el siguiente paso.</li>
</ol>

<div class="ayuda-tip">
    <strong>Tip:</strong> el <em>punto de pedido</em> sirve para que el sistema te avise
    cuando el stock cae por debajo del mínimo. Dejalo en 0 si no querés alertas.
</div>

<h3>Cómo funciona el costo</h3>
<p>
    El costo se calcula como el <strong>promedio</strong> o el <strong>mayor</strong> de los costos
    que cargues de tus proveedores. Podés elegir el método con los radios.
</p>
<p>
    Si el insumo tiene <strong>impuestos internos aplicados</strong>, el impuesto se
    <strong>suma al costo</strong> (no es recuperable). El sistema muestra el desglose.
</p>

<div class="ayuda-warning">
    <strong>Importante:</strong> el historial de cambios de costo guarda solo el
    <strong>costo base sin impuestos</strong>. Así, si el impuesto cambia por ley,
    no vas a ver variaciones falsas en los gráficos.
</div>

<h3>Cómo editar el costo de un proveedor</h3>
<ol>
    <li>En la tabla de proveedores, click en el ícono de <strong>lápiz</strong>.</li>
    <li>La fila se convierte en editable: modificá el costo y el código.</li>
    <li>Presioná <strong>Enter</strong> o click en el <strong>✓</strong> para guardar.</li>
    <li>Presioná <strong>ESC</strong> para cancelar.</li>
</ol>

<h3>Historial de cambios</h3>
<p>
    Cada vez que se modifica un costo, se registra una entrada en el historial.
    Si te equivocaste al cargar un valor, podés <strong>anular</strong> esa entrada
    (botón <strong>×</strong> en la fila). El sistema va a <em>recalcular la cadena</em>
    automáticamente para que no queden saltos falsos en los gráficos.
</p>