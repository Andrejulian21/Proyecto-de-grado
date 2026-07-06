**UNIVERSIDAD AUTÓNOMA DE BUCARAMANGA**

**Sistema de Gestión de Proyectos de Grado**

GUÍA DETALLADA PARA LA ELABORACIÓN DE

**DIAGRAMAS DE CASO DE USO**

9 Diagramas · 40 Requerimientos Funcionales · 22 No Funcionales · 36 Historias de Usuario

Floridablanca, Santander · 2025

# **Introducción**

Este documento describe detalladamente cómo construir cada uno de los 9 diagramas de caso de uso del sistema de gestión de proyectos de grado UNAB. Para cada diagrama se especifica: los requerimientos funcionales y no funcionales relacionados, los actores involucrados, los casos de uso identificados, las relaciones entre ellos (asociación, «include», «extend») y una guía paso a paso para su construcción.

**Convenciones UML utilizadas:**

* Asociación (línea sólida): conecta un actor con un caso de uso en el que participa.

* «include» (flecha punteada →): el caso de uso base siempre invoca al caso incluido.

* «extend» (flecha punteada →): el caso extensor se activa solo bajo condición específica.

* Actor primario: a la izquierda del sistema; inicia la interacción.

* Actor secundario/sistema: a la derecha; responde o ejecuta automáticamente.

Herramientas recomendadas: draw.io, Lucidchart, Visual Paradigm, StarUML o PlantUML.

| DIAGRAMA 1 — AUTENTICACIÓN Y ACCESO |
| :---: |

## **1\. Información General**

| Módulo | Autenticación y Acceso |
| :---- | :---- |
| **Reqs. Funcionales** | RF01, RF02, RF03, RF04, RF05 |
| **Historias de Usuario** | HU01, HU02, HU03 |
| **Reqs. No Funcionales relacionados** | RNF01, RNF02, RNF03, RNF04 |

## **2\. Actores**

**Estudiante:** Usuario que participa en el proyecto de grado. Accede con @unab.edu.co y solo puede ver su información.

**Director de Proyecto:** Docente responsable de guiar al estudiante. Accede con @unab.edu.co y tiene funciones de revisión.

**Coordinador Académico:** Administrador del sistema. Puede gestionar roles, usuarios y consultar auditoría.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Iniciar sesión con Google @unab.edu.co | Estudiante, Director, Coordinador | El usuario se autentica mediante OAuth2 con su cuenta Google institucional. | Principal |
| Rechazar acceso por dominio inválido | Sistema | El sistema bloquea cualquier cuenta que no sea @unab.edu.co. | Alternativo |
| Redirigir según rol | Sistema | Tras autenticar, el sistema redirige al dashboard del rol correspondiente. | «include» |
| Cerrar sesión | Estudiante, Director, Coordinador | El usuario cierra sesión de forma explícita; el token se invalida en servidor. | Principal |
| Cierre automático por inactividad | Sistema | Sesiones inactivas por más de 8 horas se cierran automáticamente. | «extend» |
| Gestionar roles de usuario | Coordinador | El coordinador asigna o cambia el rol (Estudiante/Director/Coordinador) desde el panel de administración. | Principal |
| Crear perfil automático en primer login | Sistema | Al primer inicio de sesión se crea el perfil con nombre, correo y rol. | «include» |
| Consultar log de auditoría | Coordinador | El coordinador filtra y revisa el historial de acciones del sistema (quién, cuándo, qué). | Principal |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Iniciar sesión** | **«include»** | **Redirigir según rol** | **Siempre que se autentique se evalúa el rol y se redirige.** |
| **Iniciar sesión** | **«include»** | **Crear perfil automático en primer login** | **En el primer acceso se crea el perfil del usuario.** |
| Iniciar sesión | «extend» | Rechazar acceso por dominio inválido | Si el dominio no es @unab.edu.co se activa este flujo alternativo. |
| Cerrar sesión | «extend» | Cierre automático por inactividad | La sesión también se cierra sin acción del usuario tras 8h. |

## **5\. Guía de Construcción Paso a Paso**

1. Dibuja el rectángulo del sistema con el nombre 'Sistema de Gestión de Proyectos de Grado UNAB'.

2. Coloca los actores fuera del rectángulo: Estudiante, Director y Coordinador a la izquierda; Sistema a la derecha (actor secundario).

3. Dentro del sistema dibuja 8 óvalos (casos de uso) con sus nombres.

4. Conecta cada actor a los casos de uso con líneas de asociación sólidas.

5. Dibuja la relación «include» de 'Iniciar sesión' hacia 'Redirigir según rol' con flecha punteada.

6. Dibuja la relación «include» de 'Iniciar sesión' hacia 'Crear perfil automático en primer login'.

7. Dibuja la relación «extend» de 'Cerrar sesión' hacia 'Cierre automático por inactividad'.

8. Dibuja la relación «extend» de 'Iniciar sesión' hacia 'Rechazar acceso por dominio inválido'.

9. Agrega una nota RNF en el borde del diagrama: 'RNF01: Validación 100% en servidor — RNF02: HTTPS/TLS — RNF03: RBAC backend'.

| DIAGRAMA 2 — GESTIÓN DE PROYECTOS |
| :---: |

## **1\. Información General**

| Módulo | Gestión de Proyectos |
| :---- | :---- |
| **Reqs. Funcionales** | RF06, RF07, RF08, RF09, RF10 |
| **Historias de Usuario** | HU04, HU05, HU06, HU07, HU08 |
| **Reqs. No Funcionales relacionados** | RNF07, RNF09, RNF18 |

## **2\. Actores**

**Coordinador:** Registra proyectos, cambia fases manualmente y visualiza el tablero de control global.

**Estudiante:** Consulta su fase actual, entregables pendientes y fechas límite.

**Director:** Hace seguimiento del avance de sus proyectos asignados.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Registrar proyecto de grado | Coordinador | Alta de proyecto con título, estudiantes (1-2, o 3 con justificación) y fase inicial. | Principal |
| Consultar fase activa del proyecto | Estudiante, Director | Ver la fase actual (Anteproyecto, Desarrollo, Evaluación, Presentación Final) e indicadores. | Principal |
| Avanzar de fase | Coordinador | El sistema verifica entregas obligatorias y avanza a la siguiente fase. | Principal |
| Validar entregas obligatorias | Sistema | Verifica que todas las entregas de la fase anterior están completas antes de avanzar. | «include» |
| Ver tablero de control global | Coordinador | Dashboard con todos los proyectos activos, indicadores de color y filtros. | Principal |
| Exportar tablero (PDF/Excel) | Coordinador | Descarga el resumen del tablero en PDF o Excel. | «extend» |
| Gestionar alertas automáticas | Coordinador, Sistema | Generación y envío de alertas por incumplimiento de plazos. | Principal |
| Enviar notificación de alerta | Sistema | Correo a estudiante, director y coordinador al vencer un plazo. | «include» |
| Marcar proyecto 'en riesgo de pérdida' | Sistema | Cambio de estado automático cuando el estudiante incumple 2 de 3 entregas. | «extend» |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Avanzar de fase** | **«include»** | **Validar entregas obligatorias** | **No se puede avanzar sin verificar primero las entregas.** |
| Ver tablero de control | «extend» | Exportar tablero (PDF/Excel) | El coordinador puede exportar opcionalmente el tablero. |
| **Gestionar alertas automáticas** | **«include»** | **Enviar notificación de alerta** | **Toda alerta implica envío de notificación.** |
| Gestionar alertas automáticas | «extend» | Marcar proyecto 'en riesgo' | Si hay 2 incumplimientos, se activa el marcado de riesgo. |

## **5\. Guía de Construcción Paso a Paso**

10. Dibuja el rectángulo del sistema 'Gestión de Proyectos'.

11. Actores externos: Coordinador (izquierda), Estudiante y Director (derecha/abajo).

12. Coloca 9 óvalos de casos de uso dentro del sistema.

13. Conecta Coordinador a: Registrar proyecto, Avanzar de fase, Ver tablero, Gestionar alertas.

14. Conecta Estudiante y Director a: Consultar fase activa.

15. Dibuja «include» desde Avanzar de fase hacia Validar entregas.

16. Dibuja «extend» desde Ver tablero hacia Exportar tablero.

17. Dibuja «include» desde Gestionar alertas hacia Enviar notificación.

18. Dibuja «extend» desde Gestionar alertas hacia Marcar en riesgo.

19. Nota RNF: 'RNF07: Dashboard carga \< 3s — RNF09: Disponibilidad 99%'.

| DIAGRAMA 3 — GESTIÓN DOCUMENTAL |
| :---: |

## **1\. Información General**

| Módulo | Gestión Documental |
| :---- | :---- |
| **Reqs. Funcionales** | RF11, RF12, RF13, RF14, RF15, RF16 |
| **Historias de Usuario** | HU09, HU10, HU11, HU12, HU13, HU14 |
| **Reqs. No Funcionales relacionados** | RNF08, RNF18, RNF20 |

## **2\. Actores**

**Estudiante:** Sube documentos de avance y consulta versiones anteriores con retroalimentación.

**Director:** Revisa entregas, escribe observaciones, aprueba o rechaza envíos y asigna notas.

**Coordinador:** Accede al banco de documentos finales y puede modificar notas con justificación.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Subir entregable por fase | Estudiante | Carga de PDF/DOCX (máx. 50MB) dentro del plazo activo; el sistema registra fecha y versión. | Principal |
| Verificar plazo de entrega | Sistema | Comprueba que el envío se realiza dentro del plazo activo. | «include» |
| Consultar historial de versiones | Estudiante, Director, Coordinador | Vista de todas las versiones de un documento con estado y observaciones. | Principal |
| Revisar entrega y registrar retroalimentación | Director | El director escribe observaciones vinculadas a la versión revisada. | Principal |
| Notificar revisión al estudiante | Sistema | Envío de notificación cuando el director completa la revisión. | «include» |
| Aprobar/rechazar envío formal | Director | El director habilita o bloquea el botón de envío formal del estudiante. | Principal |
| Solicitar habilitación de envío | Estudiante | El estudiante solicita permiso al director para enviar formalmente. | «include» |
| Calificar entrega | Director | Nota numérica (0-5) con justificación obligatoria; sin justificación no se guarda. | Principal |
| Consultar banco de documentos finales | Coordinador | Vista exclusiva con todos los documentos finales filtrable y exportable. | Principal |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Subir entregable** | **«include»** | **Verificar plazo de entrega** | **Siempre se valida el plazo antes de aceptar el archivo.** |
| **Revisar entrega** | **«include»** | **Notificar revisión al estudiante** | **Al guardar revisión se envía notificación automática.** |
| **Aprobar/rechazar envío** | **«include»** | **Solicitar habilitación de envío** | **El flujo inicia cuando el estudiante solicita la habilitación.** |

## **5\. Guía de Construcción Paso a Paso**

20. Dibuja el rectángulo 'Gestión Documental'.

21. Actores: Estudiante (izquierda), Director (derecha), Coordinador (abajo-derecha), Sistema (fondo).

22. 9 óvalos de casos de uso distribuidos en el sistema.

23. Conecta Estudiante a: Subir entregable, Consultar historial, Solicitar habilitación.

24. Conecta Director a: Revisar entrega, Aprobar/rechazar envío, Calificar entrega, Consultar historial.

25. Conecta Coordinador a: Consultar banco, Consultar historial.

26. Dibuja «include» desde Subir entregable hacia Verificar plazo.

27. Dibuja «include» desde Revisar entrega hacia Notificar revisión.

28. Dibuja «include» desde Aprobar/rechazar envío hacia Solicitar habilitación.

29. Nota RNF: 'RNF08: Carga asíncrona 50MB — RNF20: Retención 5 años'.

| DIAGRAMA 4 — BITÁCORA DE REUNIONES |
| :---: |

## **1\. Información General**

| Módulo | Bitácora de Reuniones |
| :---- | :---- |
| **Reqs. Funcionales** | RF17, RF18, RF19, RF20, RF21, RF22 |
| **Historias de Usuario** | HU15, HU16, HU17, HU18, HU19 |
| **Reqs. No Funcionales relacionados** | RNF05, RNF18 |

## **2\. Actores**

**Estudiante:** Registra bitácoras y firma con clave TOTP.

**Director:** Co-registra bitácoras, firma y puede ser auditado por patrones sospechosos.

**Coordinador:** Supervisa horas de reunión, detecta firmas sospechosas y consulta historial.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Registrar bitácora de reunión | Estudiante, Director | Formulario con tema, entregable, observaciones, evidencia adjunta y fecha. | Principal |
| Firmar bitácora con clave TOTP | Estudiante, Director | Clave dinámica de 6 dígitos válida 30 segundos; el timestamp se toma del servidor. | Principal |
| Validar clave TOTP | Sistema | Verifica que la clave es válida, no está vencida y no fue reutilizada. | «include» |
| Registrar firma en auditoría | Sistema | Guarda usuario, timestamp del servidor y resultado en el log de auditoría. | «include» |
| Detectar patrones de firmas sospechosas | Sistema | Alerta si un director firma más de una bitácora en 5 minutos. | Principal |
| Notificar firma sospechosa al coordinador | Sistema | Alerta visible en el panel del coordinador con el detalle del patrón. | «include» |
| Controlar horas mínimas de reunión | Sistema | Acumula horas por proyecto y alerta si está por debajo del mínimo a mitad de semestre. | Principal |
| Consultar historial de bitácoras | Director, Coordinador | Listado cronológico filtrable por fase, estado de firma y rango de fechas. | Principal |
| Exportar historial en PDF | Coordinador, Director | Descarga del historial de bitácoras de un proyecto. | «extend» |
| Notificar firma pendiente | Sistema | Recordatorio cada 24h al actor que no ha firmado transcurridas 48h. | «extend» |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Firmar bitácora** | **«include»** | **Validar clave TOTP** | **Siempre se valida la clave antes de aceptar la firma.** |
| **Firmar bitácora** | **«include»** | **Registrar firma en auditoría** | **Toda firma (válida o fallida) queda registrada.** |
| **Detectar patrones sospechosos** | **«include»** | **Notificar firma sospechosa** | **La detección siempre genera alerta al coordinador.** |
| Consultar historial | «extend» | Exportar historial en PDF | Opción disponible al consultar el historial. |
| Registrar bitácora | «extend» | Notificar firma pendiente | Si un actor no firma en 48h se envía recordatorio. |

## **5\. Guía de Construcción Paso a Paso**

30. Dibuja el rectángulo 'Bitácora de Reuniones'.

31. Actores: Estudiante y Director (izquierda), Coordinador (derecha), Sistema (secundario).

32. 10 óvalos de casos de uso en el sistema.

33. Conecta Estudiante y Director a: Registrar bitácora, Firmar bitácora.

34. Conecta Director y Coordinador a: Consultar historial.

35. Conecta Coordinador a: Detectar patrones sospechosos, Controlar horas mínimas.

36. Dibuja «include» desde Firmar bitácora hacia Validar TOTP y Registrar auditoría.

37. Dibuja «include» desde Detectar patrones hacia Notificar firma sospechosa.

38. Dibuja «extend» desde Consultar historial hacia Exportar PDF.

39. Dibuja «extend» desde Registrar bitácora hacia Notificar firma pendiente.

40. Nota RNF: 'RNF05: TOTP/RFC6238 — ventana 30s — timestamp del servidor'.

| DIAGRAMA 5 — ASIGNACIÓN DE DIRECTORES |
| :---: |

## **1\. Información General**

| Módulo | Asignación de Directores |
| :---- | :---- |
| **Reqs. Funcionales** | RF23, RF24, RF25, RF26, RF27 |
| **Historias de Usuario** | HU20, HU21, HU22, HU23, HU24 |
| **Reqs. No Funcionales relacionados** | RNF07, RNF11 |

## **2\. Actores**

**Coordinador:** Gestiona disponibilidad, asigna directores y evaluadores, genera agenda.

**Director:** Recibe notificación de asignación; su perfil es consultado por el chatbot.

**Evaluador Externo:** Recibe invitación automática por correo con los datos de la sustentación.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Configurar disponibilidad de directores | Coordinador | Registra docente y cupo máximo de proyectos por semestre; el sistema descuenta cupos al asignar. | Principal |
| Asignar director a proyecto | Coordinador | Selección de proyecto y director disponible; notificación a ambos actores. | Principal |
| Verificar cupo disponible del director | Sistema | Comprueba que el director tiene cupo antes de permitir la asignación. | «include» |
| Notificar asignación | Sistema | Envío de notificación a director y estudiante al confirmar la asignación. | «include» |
| Reasignar director con justificación | Coordinador | Cambio de director; requiere justificación escrita y queda registrado en auditoría. | «extend» |
| Asignar evaluadores externos | Coordinador | Ingreso de nombre y correo del evaluador; el sistema genera y envía la invitación. | Principal |
| Generar y enviar invitación al evaluador | Sistema | Correo formal con título, estudiantes, director, fecha, hora y lugar. | «include» |
| Generar agenda de sustentaciones | Coordinador | Configura orden, hora y lugar; el sistema exporta la agenda en PDF/Excel. | Principal |
| Enviar agenda por correo | Coordinador | Distribución masiva de la agenda a todos los participantes. | «extend» |
| Gestionar perfiles de docentes | Coordinador | CRUD de perfiles con áreas de especialización, proyectos previos y cupo. | Principal |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Asignar director** | **«include»** | **Verificar cupo disponible** | **Siempre se verifica cupo antes de asignar.** |
| **Asignar director** | **«include»** | **Notificar asignación** | **La asignación siempre genera notificación.** |
| Asignar director | «extend» | Reasignar director con justificación | Si ya hay director asignado se activa el flujo de reasignación. |
| **Asignar evaluadores externos** | **«include»** | **Generar y enviar invitación** | **Al confirmar la asignación se envía el correo automático.** |
| Generar agenda | «extend» | Enviar agenda por correo | El coordinador puede distribuir la agenda desde el sistema. |

## **5\. Guía de Construcción Paso a Paso**

41. Dibuja el rectángulo 'Asignación de Directores'.

42. Actores: Coordinador (izquierda), Director y Evaluador Externo (derecha).

43. 10 óvalos de casos de uso.

44. Conecta Coordinador a todos los casos de uso principales.

45. Conecta Director a: recibir notificación (pasivo en Notificar asignación).

46. Conecta Evaluador Externo a: Generar y enviar invitación (receptor).

47. Dibuja «include» desde Asignar director hacia Verificar cupo y Notificar asignación.

48. Dibuja «extend» desde Asignar director hacia Reasignar con justificación.

49. Dibuja «include» desde Asignar evaluadores hacia Generar y enviar invitación.

50. Dibuja «extend» desde Generar agenda hacia Enviar agenda por correo.

| DIAGRAMA 6 — CHAT Y COMUNICACIÓN |
| :---: |

## **1\. Información General**

| Módulo | Chat y Comunicación |
| :---- | :---- |
| **Reqs. Funcionales** | RF28, RF29, RF30, RF31 |
| **Historias de Usuario** | HU25, HU26, HU27, HU28 |
| **Reqs. No Funcionales relacionados** | RNF11, RNF12, RNF17 |

## **2\. Actores**

**Estudiante:** Usa el chat interno con su director y recibe notificaciones.

**Director:** Se comunica con su estudiante mediante chat y recibe notificaciones del proyecto.

**Coordinador:** Publica noticias, gestiona recursos y puede auditar el chat en modo lectura.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Enviar mensaje en chat interno | Estudiante, Director | Mensajería texto \+ archivos (PDF/DOCX/imágenes, máx. 10MB) entre estudiante y su director. | Principal |
| Auditar chat en modo lectura | Coordinador | El coordinador puede leer el chat de cualquier proyecto por razones de auditoría. | «extend» |
| Recibir notificación in-app | Estudiante, Director, Coordinador | Alerta dentro de la plataforma con contador de no leídas. | Principal |
| Recibir notificación por correo | Estudiante, Director, Coordinador | Correo automático al @unab.edu.co con resumen del evento. | «include» |
| Configurar preferencias de notificación | Estudiante, Director, Coordinador | El usuario elige qué tipos de eventos notificar por correo. | «extend» |
| Publicar noticia/anuncio | Coordinador | CRUD de publicaciones visibles para todos los usuarios, ordenadas por fecha. | Principal |
| Gestionar material de apoyo | Coordinador | Crear categorías y subir/enlazar recursos (guías, videos, bases de datos). | Principal |
| Buscar y filtrar recursos | Estudiante, Director | Búsqueda por categoría o palabra clave en el repositorio de recursos. | Principal |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Recibir notificación in-app** | **«include»** | **Recibir notificación por correo** | **Toda notificación in-app también genera un correo.** |
| Recibir notificación in-app | «extend» | Configurar preferencias | El usuario puede ajustar qué correos recibe. |
| Enviar mensaje en chat | «extend» | Auditar chat en modo lectura | El coordinador puede revisar los mensajes si es necesario. |

## **5\. Guía de Construcción Paso a Paso**

51. Dibuja el rectángulo 'Chat y Comunicación'.

52. Actores: Estudiante y Director (izquierda), Coordinador (derecha).

53. 8 óvalos de casos de uso.

54. Conecta Estudiante y Director a: Enviar mensaje, Recibir notificación, Buscar recursos.

55. Conecta Coordinador a: Publicar noticia, Gestionar material, Recibir notificación, Auditar chat.

56. Dibuja «include» desde Notificación in-app hacia Notificación por correo.

57. Dibuja «extend» desde Notificación in-app hacia Configurar preferencias.

58. Dibuja «extend» desde Enviar mensaje hacia Auditar chat.

59. Nota RNF: 'RNF17: Interfaz en español colombiano — RNF11: Responsivo'.

| DIAGRAMA 7 — IA – CHATBOT DE ORIENTACIÓN |
| :---: |

## **1\. Información General**

| Módulo | IA – Chatbot de Orientación |
| :---- | :---- |
| **Reqs. Funcionales** | RF32, RF33, RF34 |
| **Historias de Usuario** | HU29, HU30 |
| **Reqs. No Funcionales relacionados** | RNF07, RNF12 |

## **2\. Actores**

**Estudiante:** Consulta el chatbot para orientarse sobre el proceso y recibir sugerencias de directores.

**Coordinador:** Consulta estadísticas de uso del chatbot para mejorar el material de apoyo.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Consultar chatbot de orientación | Estudiante | Preguntas en lenguaje natural sobre el proceso, criterios y estructura documental. | Principal |
| Recibir sugerencias de directores afines | Estudiante | El estudiante describe su idea y recibe lista de directores con cupo disponible y justificación. | «include» |
| Consultar perfil de docentes (IA) | Sistema | El motor de IA accede a los perfiles de directores (RF27) para generar las recomendaciones. | «include» |
| Generar resumen de conversación | Estudiante | El chatbot crea un resumen exportable de la sesión de consulta. | «extend» |
| Autorizar envío de resumen al director | Estudiante | El estudiante aprueba el envío de su propuesta al director sugerido. | «extend» |
| Enviar correo automático al director sugerido | Sistema | Correo con nombre del estudiante, descripción de la propuesta y áreas temáticas. | «include» |
| Registrar interacciones del chatbot | Sistema | Registro anonimizado de categorías de preguntas frecuentes. | Principal |
| Consultar estadísticas del chatbot | Coordinador | Resumen estadístico de consultas frecuentes para mejorar el material de apoyo. | Principal |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Consultar chatbot** | **«include»** | **Recibir sugerencias de directores** | **Al describir su propuesta el chatbot siempre consulta los perfiles.** |
| **Recibir sugerencias** | **«include»** | **Consultar perfil de docentes (IA)** | **La sugerencia requiere acceder a los perfiles actualizados.** |
| Consultar chatbot | «extend» | Generar resumen de conversación | Opcionalmente el estudiante puede exportar el resumen. |
| Generar resumen | «extend» | Autorizar envío de resumen | Con el resumen generado el estudiante puede autorizar el envío. |
| **Autorizar envío** | **«include»** | **Enviar correo automático al director** | **La autorización explícita desencadena el envío del correo.** |

## **5\. Guía de Construcción Paso a Paso**

60. Dibuja el rectángulo 'IA – Chatbot de Orientación'.

61. Actores: Estudiante (izquierda), Coordinador (derecha), Sistema IA (fondo).

62. 8 óvalos de casos de uso.

63. Conecta Estudiante a: Consultar chatbot, Generar resumen, Autorizar envío.

64. Conecta Coordinador a: Consultar estadísticas.

65. Dibuja «include» desde Consultar chatbot hacia Recibir sugerencias y desde Recibir sugerencias hacia Consultar perfiles.

66. Dibuja «extend» desde Consultar chatbot hacia Generar resumen.

67. Dibuja «extend» desde Generar resumen hacia Autorizar envío.

68. Dibuja «include» desde Autorizar envío hacia Enviar correo.

69. Nota: El actor 'Sistema IA' se conecta a 'Registrar interacciones' y 'Consultar perfiles'.

| DIAGRAMA 8 — IA – ANÁLISIS SEMÁNTICO DE DOCUMENTOS |
| :---: |

## **1\. Información General**

| Módulo | IA – Análisis Semántico de Documentos |
| :---- | :---- |
| **Reqs. Funcionales** | RF35, RF36, RF37 |
| **Historias de Usuario** | HU31, HU32, HU33 |
| **Reqs. No Funcionales relacionados** | RNF07, RNF12 |

## **2\. Actores**

**Estudiante:** Recibe retroalimentación orientativa automática tras subir un documento.

**Director:** Define criterios de evaluación por fase y recibe alertas de coherencia junto con las entregas.

**Coordinador:** También puede definir criterios; supervisa los informes generados.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Analizar documento automáticamente | Sistema | Al subir un documento, el motor semántico lo analiza contra los criterios de la fase activa. | Principal |
| Generar informe de retroalimentación orientativa | Sistema | Informe con aspectos que cumplen y aspectos que requieren mejora; incluye aviso de no reemplaza al director. | «include» |
| Consultar informe de análisis | Estudiante, Director | Acceso al informe generado antes de la revisión formal del director. | Principal |
| Configurar criterios de evaluación por fase | Director, Coordinador | CRUD de criterios (nombre, descripción, peso) por fase; aplican a nuevas entregas. | Principal |
| Generar alerta de coherencia documental | Sistema | Alerta informativa si el documento no cumple criterios mínimos de la fase. | «extend» |
| Mostrar alerta al estudiante antes del envío | Sistema | La alerta se muestra antes de confirmar el envío; el estudiante puede igualmente enviar. | «include» |
| Notificar alerta al director con la entrega | Sistema | El director recibe la alerta junto con la notificación de nueva entrega. | «include» |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Analizar documento** | **«include»** | **Generar informe orientativo** | **El análisis siempre produce un informe.** |
| Analizar documento | «extend» | Generar alerta de coherencia | Si hay inconsistencias graves se genera la alerta. |
| **Generar alerta de coherencia** | **«include»** | **Mostrar alerta al estudiante** | **La alerta siempre se muestra al estudiante.** |
| **Generar alerta de coherencia** | **«include»** | **Notificar alerta al director** | **El director también recibe la alerta.** |

## **5\. Guía de Construcción Paso a Paso**

70. Dibuja el rectángulo 'IA – Análisis Semántico'.

71. Actores: Estudiante (izquierda), Director y Coordinador (derecha), Motor IA/Sistema (fondo).

72. 7 óvalos de casos de uso.

73. El actor 'Sistema/Motor IA' se conecta a: Analizar documento, Generar informe, Generar alerta.

74. Conecta Estudiante a: Consultar informe, Mostrar alerta (receptor).

75. Conecta Director y Coordinador a: Configurar criterios.

76. Conecta Director a: Consultar informe, Notificar alerta (receptor).

77. Dibuja «include» desde Analizar documento hacia Generar informe.

78. Dibuja «extend» desde Analizar documento hacia Generar alerta de coherencia.

79. Dibuja «include» desde Generar alerta hacia Mostrar alerta al estudiante y Notificar alerta al director.

80. Nota: Agregar aviso 'Este análisis es orientativo y no reemplaza el criterio del director'.

| DIAGRAMA 9 — EVALUACIÓN Y CALIFICACIONES |
| :---: |

## **1\. Información General**

| Módulo | Evaluación y Calificaciones |
| :---- | :---- |
| **Reqs. Funcionales** | RF38, RF39, RF40 |
| **Historias de Usuario** | HU34, HU35, HU36 |
| **Reqs. No Funcionales relacionados** | RNF18, RNF20 |

## **2\. Actores**

**Director:** Registra la nota de la presentación final con justificación.

**Evaluador Externo:** Ingresa su nota individual para el promedio ponderado.

**Docente de Metodología:** Registra notas de desempeño por corte de la asignatura.

**Coordinador:** Supervisa notas, define pesos de ponderación y genera reportes consolidados.

## **3\. Casos de Uso**

| Caso de Uso | Actor(es) | Descripción | Tipo |
| ----- | ----- | ----- | ----- |
| Registrar nota de presentación final | Director, Evaluador Externo | Cada evaluador ingresa nota (0-5) y justificación obligatoria por separado. | Principal |
| Calcular nota promedio ponderada | Sistema | El sistema promedia las notas según los pesos definidos por el coordinador. | «include» |
| Solicitar aprobación para modificar nota | Director, Evaluador Externo | Una nota registrada no puede modificarse sin aprobación del coordinador. | «extend» |
| Aprobar modificación de nota | Coordinador | El coordinador autoriza cambios en notas ya registradas. | Principal |
| Registrar nota de metodología por corte | Docente de Metodología | Nota de desempeño vinculada al proyecto y visible para el coordinador. | Principal |
| Generar reporte consolidado de calificaciones | Coordinador | Reporte exportable (PDF/Excel) con entregas, metodología y presentación de uno o todos los proyectos. | Principal |
| Filtrar reporte por semestre/proyecto | Coordinador | El reporte puede generarse para un proyecto individual o para todo el semestre. | «extend» |

## **4\. Relaciones entre Casos de Uso**

| Desde | Tipo | Hacia | Descripción |
| ----- | ----- | ----- | ----- |
| **Registrar nota de presentación** | **«include»** | **Calcular nota promedio** | **Al registrar la última nota el sistema calcula el promedio.** |
| Registrar nota de presentación | «extend» | Solicitar aprobación para modificar | Si se intenta modificar una nota ya guardada se activa este flujo. |
| Generar reporte consolidado | «extend» | Filtrar reporte por semestre | El coordinador puede acotar el reporte por semestre o proyecto. |

## **5\. Guía de Construcción Paso a Paso**

81. Dibuja el rectángulo 'Evaluación y Calificaciones'.

82. Actores: Director y Evaluador Externo (izquierda), Docente de Metodología (abajo-izquierda), Coordinador (derecha).

83. 7 óvalos de casos de uso.

84. Conecta Director y Evaluador Externo a: Registrar nota de presentación.

85. Conecta Docente de Metodología a: Registrar nota de metodología.

86. Conecta Coordinador a: Aprobar modificación de nota, Generar reporte consolidado.

87. Dibuja «include» desde Registrar nota hacia Calcular nota promedio.

88. Dibuja «extend» desde Registrar nota hacia Solicitar aprobación para modificar.

89. Dibuja «extend» desde Generar reporte hacia Filtrar reporte.

90. Nota RNF: 'RNF18: Transacciones BD para evitar conflictos — RNF20: Retención 5 años'.

# **Resumen General de Diagramas**

| N° | Módulo | Actores | Casos de Uso | RFs | HUs |
| ----- | ----- | ----- | ----- | ----- | ----- |
| 1 | Autenticación y Acceso | Estudiante, Director de Proyecto, Coordinador Académico | 8 | RF01, RF02, RF03, RF04, RF05 | HU01, HU02, HU03 |
| 2 | Gestión de Proyectos | Coordinador, Estudiante, Director | 9 | RF06, RF07, RF08, RF09, RF10 | HU04, HU05, HU06, HU07, HU08 |
| 3 | Gestión Documental | Estudiante, Director, Coordinador | 9 | RF11, RF12, RF13, RF14, RF15, RF16 | HU09, HU10, HU11, HU12, HU13, HU14 |
| 4 | Bitácora de Reuniones | Estudiante, Director, Coordinador | 10 | RF17, RF18, RF19, RF20, RF21, RF22 | HU15, HU16, HU17, HU18, HU19 |
| 5 | Asignación de Directores | Coordinador, Director, Evaluador Externo | 10 | RF23, RF24, RF25, RF26, RF27 | HU20, HU21, HU22, HU23, HU24 |
| 6 | Chat y Comunicación | Estudiante, Director, Coordinador | 8 | RF28, RF29, RF30, RF31 | HU25, HU26, HU27, HU28 |
| 7 | IA – Chatbot de Orientación | Estudiante, Coordinador | 8 | RF32, RF33, RF34 | HU29, HU30 |
| 8 | IA – Análisis Semántico de Documentos | Estudiante, Director, Coordinador | 7 | RF35, RF36, RF37 | HU31, HU32, HU33 |
| 9 | Evaluación y Calificaciones | Director, Evaluador Externo, Docente de Metodología, Coordinador | 7 | RF38, RF39, RF40 | HU34, HU35, HU36 |

Total: 9 diagramas · 76 casos de uso identificados · 40 RFs cubiertos · 36 HUs relacionadas.