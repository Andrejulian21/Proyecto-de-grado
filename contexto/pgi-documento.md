Sistema centralizado para el seguimiento y control de proyectos de grado del programa
de ingeniería de sistema de la UNAB
Autor(es):
Julian Arteaga Faria
Miguel Afanador Quintero
Anteproyecto presentado para la asignatura Proyecto de Grado I
Director(a):
Javier Pinzon Castellanos
Co-Director:
Fabian Suarez Carvajal
Universidad Autónoma de Bucaramanga
Facultad de Ingeniería
Ingeniería de Sistemas
Bucaramanga Santander
24/02/26

Tabla de Contenido
1. Introducción..........................................................................................................................4
2. Planteamiento del Problema................................................................................................4
2.1. Árbol del problema........................................................................................................7
2.2. Pregunta problema.........................................................................................................7
3. Justificación del proyecto....................................................................................................8
4. Objetivos...............................................................................................................................9
4.1. Objetivo general............................................................................................................9
4.2. Objetivos específicos.....................................................................................................9
5. Alcance y delimitación.........................................................................................................9
5.1. Alcance..........................................................................................................................9
5.2. Delimitación................................................................................................................10
6. Marco de Referencia..........................................................................................................10
6.1. Marco Teórico.............................................................................................................10
6.1.1. Sistemas de Información....................................................................................10
6.1.2. Gestión de Proyectos Académicos.....................................................................11
6.1.3. Workflow y Trazabilidad de Procesos Académicos...........................................11
6.1.4. Ingeniería de Requisitos y Diseño de Sistemas..................................................12
6.1.5. Seguridad en Sistemas Web...............................................................................12
6.1.6. Sistemas Web para la Gestión Académica.........................................................12
6.1.7. Gestión Documental Digital...............................................................................13
6.1.8. Arquitectura de Sistemas de Información..........................................................13
6.1.9. Inteligencia Artificial Aplicada al Proceso de Grado.........................................13
6.1.10. Experiencia de Usuario y Usabilidad...............................................................15
6.2. Estado del Arte............................................................................................................15
6.2.1. Sistemas de gestión y centralización de proyectos académicos.........................16
6.2.2. Metodologías y marcos formales para el diseño y gestión de proyectos...........16
6.2.3. Seguimiento del desarrollo y medición de productividad..................................16
6.2.4. Analítica avanzada, motivación y apoyo a la toma de decisiones......................17
6.2.5. Seguridad y arquitecturas modernas en sistemas académicos............................17
6.2.6. Análisis crítico y oportunidad de investigación.................................................17
6.2.7. Tabla de Artículos..............................................................................................18
6.3. Marco Normativo y Legal...........................................................................................18
6.3.1. Ley 1581 de 2012 – Protección de Datos Personales.........................................19
6.3.2. Ley 1266 de 2008 – Hábeas Data.......................................................................19
6.3.3. Ley 23 de 1982 y Ley 1915 de 2018 – Derechos de Autor................................19
6.3.4. Ley 1341 de 2009 y Ley 1978 de 2019 – Acceso a las Tecnologías de la
Información..................................................................................................................20
6.3.5. Norma ISO/IEC 25010 – Calidad del Software.................................................20

6.3.6. Norma ISO/IEC 27001 – Seguridad de la Información.....................................20
6.3.7. Política de Propiedad Intelectual de la Universidad Autónoma de Bucaramanga.
20
6.3.8. Políticas Institucionales de la Universidad Autónoma de Bucaramanga...........21
6.4. Consideraciones éticas................................................................................................21
7. Diseño Metodológico..........................................................................................................22
7.2. Adaptación de fases PMBOK.....................................................................................23
7.3. Fases del proyecto.......................................................................................................24
7.4. Nivel de madurez tecnológica (TRL)..........................................................................27
7.5. Recursos y Presupuesto...............................................................................................27
7.6. Metodología de desarrollo...........................................................................................29
7.7. Diseño de Ingeniería....................................................................................................31
7.8. Validación de Resultados............................................................................................32
7.8.1. Pruebas Funcionales Internas.............................................................................32
7.8.2. Prueba Piloto con Usuarios Reales.....................................................................32
7.8.3. Evaluación de Usabilidad Instrumento SUS......................................................32
7.8.4. Documentación de Hallazgos.............................................................................32
8. Resultados esperados.........................................................................................................33
9. Referencias Bibliográficas.................................................................................................34
10. Anexos...............................................................................................................................38

1. Introducción
En el programa de Ingeniería de Sistemas de la Universidad Autónoma de Bucaramanga, el
proceso de gestión de proyectos de grado se apoya actualmente en Moodle, una plataforma
diseñada para la administración de cursos regulares. Esta herramienta no cuenta con
funcionalidades para el seguimiento estructurado por fases, el control de versiones de
documentos ni la asignación formal de directores y evaluadores. Como resultado, estudiantes,
directores y coordinación académica recurren al correo electrónico y canales de mensajería
informal para coordinar entregas, resolver observaciones y hacer seguimiento al avance de
los proyectos. Al respecto, Wang (2024) señala que el uso de una herramienta especializada
optimiza el proceso evaluativo y de desarrollo del proyecto, lo que respalda la necesidad de
contar con una solución diseñada específicamente para este tipo de proceso académico. Este
proyecto propone un sistema web centralizado que organice ese proceso en una sola
plataforma, con módulos específicos para cada actor e integración de dos funcionalidades de
inteligencia artificial: un asistente conversacional que orienta al estudiante en la selección de
director de proyecto según el perfil docente, y un módulo de análisis automático de avances
que evalúa cada entrega frente a los criterios definidos para esa fase del proceso de grado.
2. Planteamiento del Problema
El desarrollo del proyecto de grado en la carrera de Ingeniería de Sistemas constituye un
proceso académico que involucra múltiples actores, etapas y flujos de información. Este
proceso requiere una adecuada gestión de documentos, una comunicación constante entre
estudiantes y directores de proyecto, así como una organización eficiente por parte de la
coordinación académica encargada de supervisar los trabajos. Sin embargo, actualmente en la
Universidad Autónoma de Bucaramanga no se dispone de una herramienta tecnológica
especializada para la gestión de este proceso, lo que genera diversas dificultades en su
desarrollo y seguimiento.
Seguimiento de la documentación entre los alumnos y el director de proyecto
Durante el desarrollo del proyecto de grado se generan múltiples documentos y entregables,
tales como propuestas iniciales, avances parciales, informes de progreso, bitácoras de
seguimiento y versiones finales del trabajo. La gestión de esta documentación requiere no
solo eficiencia en el intercambio de archivos, sino también seguridad en el manejo de la
información y trazabilidad en los cambios realizados a lo largo del proceso.

Actualmente, gran parte de estas actividades se gestionan mediante Moodle, una plataforma
diseñada principalmente para la administración de cursos académicos. Aunque esta
herramienta permite la entrega de archivos y la comunicación básica entre estudiantes y
docentes, no cuenta con funcionalidades especializadas para el seguimiento estructurado de
proyectos de grado. Procesos como el registro de bitácoras de trabajo, el seguimiento
detallado de avances o la aplicación de criterios de evaluación específicos suelen realizarse
de manera manual o mediante mecanismos poco automatizados, lo que puede afectar la
organización de la información y la eficiencia del proceso.
Proceso de elección de director de proyecto y de evaluadores
Otro aspecto relevante dentro del proceso de desarrollo del proyecto de grado es la asignación
del director de proyecto y de los evaluadores que participarán en las diferentes etapas de
revisión y validación del trabajo. Este proceso requiere la gestión de información relacionada
con la disponibilidad de los docentes, las áreas de interés o especialización, y la distribución
equilibrada de proyectos entre los profesores del programa.
En la actualidad, estas actividades suelen ser gestionadas manualmente por el encargado de
coordinar los proyectos de grado dentro del programa de Ingeniería de Sistemas. Esto implica
recopilar y comunicar a los estudiantes la información sobre los docentes disponibles para
dirigir proyectos, realizar la asignación de evaluadores y llevar el control administrativo de
los distintos proyectos en desarrollo. La ausencia de una herramienta que centralice esta
información puede generar dificultades en la organización, retrasos en la asignación de roles
y una mayor carga administrativa para el responsable del proceso.
Falta de una herramienta especializada
A pesar de la importancia del proyecto de grado dentro del proceso formativo, actualmente
no existe una herramienta tecnológica dedicada específicamente a la gestión integral de este
proceso. El uso de plataformas diseñadas para la gestión de cursos, como Moodle, no
responde completamente a las necesidades particulares del desarrollo de proyectos de grado,
ya que estas herramientas están orientadas principalmente a la administración de actividades
académicas regulares.

Esta situación evidencia la necesidad de contar con un sistema que permita gestionar de
forma más adecuada las distintas etapas del proyecto de grado, facilitando el seguimiento de
avances, la administración de documentos, la asignación de directores y evaluadores, y la
organización general de los proyectos dentro del programa académico. La ausencia de una
solución especializada limita la eficiencia del proceso y dificulta la implementación de
mecanismos más estructurados para su gestión (Wang, 2024).
Vale la pena señalar que en el programa de Ingeniería de Sistemas de la UNAB se han
desarrollado previamente proyectos de grado con propósitos similares al aquí planteado; sin
embargo, ninguno de estos sistemas fue adoptado ni implementado de forma oficial dentro
del programa. Como resultado, la coordinación académica y los estudiantes continúan
utilizando Moodle y canales informales como principal mecanismo de gestión, lo que
confirma que el problema persiste y que las soluciones desarrolladas internamente no han
logrado trascender la fase académica para convertirse en herramientas de uso institucional.
Este antecedente refuerza la pertinencia del presente proyecto, que contempla desde su
formulación una validación con usuarios reales como requisito para su entrega final.

2.1. Árbol del problema
2.2. Pregunta problema.
¿Cómo se puede desarrollar un sistema centralizado que apoye el seguimiento y control de las
diferentes fases del proyecto de grado; Anteproyecto, desarrollo, evaluación y presentación
final del programa de Ingeniería de Sistemas de la UNAB?

3. Justificación del proyecto
El proceso de gestión de proyectos de grado en el programa de Ingeniería de Sistemas de la
UNAB presenta dificultades concretas que afectan a los tres actores involucrados:
estudiantes, directores y coordinación académica. Actualmente, el seguimiento de entregas se
realiza a través de Moodle, correo electrónico y mensajería informal, lo que dificulta
mantener un registro ordenado de versiones, observaciones y estados de avance por proyecto.
Para los estudiantes, la ausencia de una ruta de progreso visible genera incertidumbre sobre el
estado de sus entregas y los criterios de aprobación en cada fase. Para los directores, revisar
documentos dispersos en distintos canales implica una carga adicional que no está
relacionada con la orientación académica en sí. Para la coordinación, llevar el control de qué
proyectos están activos, quién los dirige y en qué etapa se encuentran depende en gran
medida de registros manuales susceptibles a errores y desactualización.
Este proyecto se justifica porque ninguna de las herramientas que actualmente se usan en el
programa fue diseñada para gestionar el ciclo completo de un proyecto de grado. Moodle está
orientado a la administración de cursos por semestre, no al seguimiento longitudinal de un
proceso que puede extenderse por más de un año académico e involucra etapas, roles y
criterios de evaluación distintos a los de una asignatura regular.
La propuesta responde a una necesidad identificada directamente con los actores del proceso:
centralizar en una sola plataforma la gestión documental, el seguimiento por fases, la
asignación de directores y evaluadores, y la comunicación entre los participantes. A esto se
suma un componente de inteligencia artificial con dos funcionalidades concretas: un asistente
conversacional que permite al estudiante describir su propuesta de proyecto y recibir una
orientación sobre qué docentes del programa podrían ser directores adecuados según su perfil
académico y las asignaturas que imparten, y un módulo de análisis automático de entregas
que evalúa cada avance subido por el estudiante frente a los criterios establecidos para esa
fase, generando retroalimentación de apoyo sin reemplazar el criterio del director.
El desarrollo es viable dentro del tiempo y los recursos disponibles, ya que se apoya en
tecnologías de código abierto ampliamente documentadas y en conocimientos adquiridos
durante la carrera. Su implementación no requiere integración con sistemas institucionales
externos ni modificaciones a la infraestructura existente de la universidad. Desde el punto de
vista formativo, el proyecto permite aplicar de forma integrada competencias en análisis de
requerimientos, diseño de sistemas de información, desarrollo web y procesamiento de
lenguaje natural, áreas directamente alineadas con el perfil del Ingeniero de Sistemas de la
UNAB.

4. Objetivos
4.1. Objetivo general
Desarrollar un sistema centralizado que apoye el seguimiento y control de las fases del
proyecto de grado del programa de Ingeniería de Sistemas de la UNAB.
4.2. Objetivos específicos
1. Diagnosticar el proceso actual de gestión de proyectos de grado del programa de
Ingeniería de Sistemas de la UNAB mediante la recolección y análisis de información
con los actores involucrados, con el fin de especificar los requerimientos funcionales
y no funcionales que debe satisfacer el sistema centralizado.
2. Diseñar la arquitectura y los componentes del sistema web centralizado mediante el
modelado de la estructura técnica, el modelo de datos y las interfaces de usuario, con
el fin de establecer las bases que guíen el proceso de implementación.
3. Implementar el sistema web centralizado mediante el desarrollo de sus módulos
funcionales e integración de componentes de inteligencia artificial, con el fin de
construir una solución que apoye el seguimiento y control de las fases del proyecto de
grado.
4. Evaluar el funcionamiento del sistema desarrollado para verificar su capacidad de
apoyar el seguimiento y control de los proyectos de grado, mediante pruebas
funcionales y escenarios de uso que permitan analizar su desempeño y utilidad en el
contexto académico.
5. Alcance y delimitación
5.1. Alcance
El proyecto comprende el análisis, diseño, desarrollo y validación de un sistema web
centralizado para la gestión y seguimiento de proyectos de grado del programa de Ingeniería
de Sistemas de la UNAB, cubriendo las cuatro fases del proceso: anteproyecto, desarrollo,
evaluación y presentación final.
El sistema incluirá los siguientes módulos: autenticación y control de acceso diferenciado por
perfil, gestión documental con carga y versionado de entregables, seguimiento del avance por

fases, asignación de directores y evaluadores por parte de la coordinación, comunicación
interna entre actores, y un componente de inteligencia artificial compuesto por dos
funcionalidades: un asistente conversacional que orienta al estudiante en la selección de
director de proyecto según el perfil docente, y un módulo de análisis automático que evalúa
cada entrega frente a los criterios definidos para la fase correspondiente, generando
retroalimentación de apoyo para el estudiante y el director. No se contempla la
automatización de la evaluación académica ni la integración con sistemas institucionales
externos en esta versión.
Los entregables del proyecto son: el sistema web funcional, la documentación técnica del
sistema y el informe de resultados de la validación con usuarios reales. El sistema está
dirigido a tres tipos de usuarios: estudiantes, directores de proyecto y coordinación académica
del programa.
5.2. Delimitación
El sistema se desarrolla exclusivamente para el programa de Ingeniería de Sistemas de la
UNAB y no contempla su extensión a otros programas o instituciones. No incluye integración
con plataformas institucionales externas como Moodle ni con sistemas de información
administrativos de la universidad. No se implementarán funcionalidades de firma digital,
pagos ni trámites administrativos formales. El módulo de análisis semántico se limita al
procesamiento de documentos en formato de texto y no incluye procesamiento de imágenes,
audio ni otros formatos. La validación se realizará durante el periodo académico
correspondiente al desarrollo del proyecto, por lo que los resultados no son generalizables a
otros contextos institucionales.
6. Marco de Referencia
6.1. Marco Teórico
Para el desarrollo de este proyecto fue necesario revisar una serie de conceptos técnicos y
académicos que sirven de base para las decisiones de diseño e implementación del sistema. A
continuación se presentan los fundamentos conceptuales más relevantes, organizados según
su relación directa con el sistema propuesto para el programa de Ingeniería de Sistemas de la
UNAB.
6.1.1. Sistemas de Información
Un sistema de información es un conjunto organizado de componentes tecnológicos,
humanos y procedimentales orientados a recopilar, procesar, almacenar y distribuir
información para apoyar la toma de decisiones dentro de una organización. Esta definición
básica es relevante para este proyecto porque el sistema propuesto debe cumplir exactamente
ese rol dentro del proceso de grado de la UNAB.

En el contexto del programa de Ingeniería de Sistemas, los procesos académicos como la
gestión de proyectos de grado involucran múltiples actores —estudiantes, directores y
coordinadores— que requieren acceder a información centralizada y actualizada. Sin un
sistema que integre esa información, se generan los problemas de dispersión y falta de
trazabilidad que motivaron este proyecto.
La centralización de la información, característica fundamental de un sistema de información
bien diseñado, permite reducir la dependencia de herramientas fragmentadas como el correo
electrónico y mejorar la visibilidad del estado real de cada proyecto de grado.
6.1.2. Gestión de Proyectos Académicos
La gestión de proyectos es una disciplina orientada a planificar, organizar y controlar
actividades con el fin de alcanzar objetivos específicos dentro de un tiempo y alcance
definidos. Trasladado al contexto universitario, un proyecto de grado puede entenderse como
un proyecto formativo que exige planificación clara, seguimiento continuo y evaluación por
fases.
Durante el desarrollo de un proyecto de grado se generan múltiples entregables: propuesta
inicial, avances parciales, informes de progreso y versión final. Gestionar todos estos
elementos de forma adecuada resulta crítico para garantizar la calidad del proceso y facilitar
la supervisión por parte del director asignado.
Para este sistema, los principios de gestión de proyectos se aplican en el diseño del módulo
de seguimiento por fases, donde cada etapa del proceso de grado corresponde a un hito con
entregables, fechas y mecanismos de retroalimentación definidos.
6.1.3. Workflow y Trazabilidad de Procesos Académicos
El concepto de workflow, o flujo de trabajo, hace referencia a la secuencia estructurada de
actividades necesarias para completar un proceso. En el sistema propuesto, este concepto se
aplica directamente: el proceso de grado tiene etapas definidas —desde la presentación del
anteproyecto hasta la sustentación final— y cada una requiere acciones específicas de los
diferentes actores.
La trazabilidad, por su parte, es la capacidad de registrar y rastrear las acciones realizadas
durante un proceso: quién hizo qué, cuándo lo hizo y qué cambios generó. En el contexto del
programa de Ingeniería de Sistemas de la UNAB, la falta de trazabilidad es uno de los
problemas más evidentes del esquema actual, dado que las observaciones de los directores y
los historial de entregas quedan dispersos entre correos y archivos sin ninguna estructura.
El sistema propuesto busca precisamente subsanar esto, registrando de forma automática cada
entrega, cada observación y cada cambio de estado dentro del proceso de grado.

6.1.4. Ingeniería de Requisitos y Diseño de Sistemas
La ingeniería de requisitos es la disciplina encargada de identificar, analizar y documentar las
necesidades de los usuarios de un sistema. Su correcta aplicación es determinante para que el
sistema desarrollado responda de manera real a lo que los actores del proceso necesitan, y no
simplemente a lo que el equipo de desarrollo asume que necesitan.
Para este proyecto, se llevó a cabo un proceso de levantamiento de requisitos con los actores
del programa de Ingeniería de Sistemas de la UNAB, lo que permitió identificar las
funcionalidades prioritarias del sistema. Algunos referentes académicos —como el enfoque
de Design Theory aplicado en el sistema FYPMS— proponen el uso de meta-requisitos para
validar la completitud de los requerimientos antes de iniciar el desarrollo, lo cual reduce el
riesgo de inconsistencias durante la implementación.
6.1.5. Seguridad en Sistemas Web
La seguridad es un requisito no negociable en cualquier sistema que maneje información
académica sensible. El sistema propuesto gestionará datos de estudiantes, documentos de
proyectos y observaciones de docentes, lo que exige implementar medidas de protección
desde las etapas iniciales del diseño.
Entre las prácticas incorporadas en el diseño del sistema se incluyen la validación de entradas
de usuario, la protección frente a ataques de inyección SQL, la gestión segura de sesiones y
mecanismos robustos de autenticación. Como referente directo, el sistema FIPOS (Cahyanto
et al., 2022) incorporó autenticación mediante contraseñas de un solo uso (OTP) y siguió las
directrices de OWASP, lo que permitió reducir vulnerabilidades comunes en plataformas
académicas similares. Para el sistema de la UNAB se tomarán en cuenta estas prácticas como
base para la capa de seguridad.
6.1.6. Sistemas Web para la Gestión Académica
Una aplicación web permite el acceso remoto a servicios mediante un navegador, desde
cualquier dispositivo y ubicación. Esta característica es especialmente útil en el contexto del
programa de Ingeniería de Sistemas de la UNAB, donde directores y estudiantes no siempre
coinciden en el mismo espacio físico y requieren interactuar de forma asíncrona con el
proceso de grado.
Si bien existen plataformas LMS —como Moodle o Blackboard— que gestionan aspectos
generales de los cursos, estas herramientas no están diseñadas para administrar procesos
complejos como el seguimiento por fases de un proyecto de grado. Por eso, el desarrollo de
un sistema especializado resulta más adecuado que intentar adaptar una plataforma genérica a
necesidades muy específicas del programa.

Las ventajas concretas de un sistema web propio incluyen la posibilidad de modelar
exactamente el flujo del proceso de grado de la UNAB, integrar la comunicación entre
actores, gestionar documentos y generar reportes ajustados a los criterios del programa.
6.1.7. Gestión Documental Digital
A lo largo de un proyecto de grado se genera una cantidad considerable de documentos:
propuesta inicial, actas de reunión, avances parciales, borradores y versión final. La gestión
eficiente de todos estos archivos es uno de los puntos más débiles del esquema actual del
programa, donde los documentos circulan por correo electrónico sin ningún control de
versiones ni estructura de almacenamiento.
La gestión documental digital abarca los métodos y herramientas para administrar el ciclo de
vida de documentos electrónicos, incluyendo su creación, almacenamiento, consulta, control
de versiones y archivo. Para el sistema propuesto, esto se traduce en un módulo de gestión
documental que permita subir, organizar y consultar los documentos de cada proyecto,
manteniendo un historial claro de versiones y observaciones.
6.1.8. Arquitectura de Sistemas de Información
La arquitectura de software define la estructura fundamental de un sistema, sus componentes
y las relaciones entre ellos. Una buena decisión arquitectónica facilita el mantenimiento, la
escalabilidad y la evolución del sistema a largo plazo.
Para el sistema de seguimiento de proyectos de grado de la UNAB se adopta una arquitectura
por capas, separando la interfaz de usuario (frontend), la lógica de negocio (backend) y la
gestión de datos (base de datos). Esta separación mejora la organización del código y facilita
el trabajo en equipo durante el desarrollo.
Adicionalmente, el uso de una arquitectura basada en APIs permite que el sistema pueda
integrarse en el futuro con otros servicios institucionales, como plataformas de autenticación
corporativa o sistemas de registro académico, sin necesidad de rediseñar la arquitectura desde
cero.
6.1.9. Inteligencia Artificial Aplicada al Proceso de Grado
Uno de los principales elementos diferenciadores del sistema propuesto es la incorporación
de funcionalidades basadas en inteligencia artificial orientadas al análisis automático de
documentos académicos. A diferencia de los sistemas tradicionales de gestión de proyectos,
cuyo enfoque suele limitarse al almacenamiento y organización de archivos, el presente
proyecto busca incorporar mecanismos de apoyo que permitan analizar entregables
estudiantiles y generar retroalimentación orientativa de acuerdo con criterios definidos
previamente por los docentes.

Para ello, el sistema utilizará técnicas asociadas al Natural Language Processing, área de la
inteligencia artificial enfocada en el procesamiento e interpretación del lenguaje humano.
Estas técnicas permiten realizar tareas como análisis semántico, clasificación textual,
extracción de conceptos y comparación contextual de documentos. En los últimos años, este
tipo de tecnologías ha comenzado a utilizarse en entornos educativos para mejorar procesos
de búsqueda, evaluación y organización de información académica.
Trabajos realizados destacan que los enfoques de búsqueda semántica permiten interpretar
textos más allá de coincidencias exactas de palabras, utilizando representaciones contextuales
del lenguaje para identificar relaciones conceptuales entre documentos educativos
(Choudhary et al. 2024). Este enfoque resulta relevante para sistemas académicos donde los
textos pueden expresar ideas similares utilizando terminología diferente, situación frecuente
en propuestas y avances de proyectos de grado.
De manera similar, Mamurjonova et al. (2025) proponen un modelo de análisis semántico
aplicado a documentación técnica educativa mediante el uso de embeddings y técnicas de
similitud semántica. Los autores señalan que los métodos tradicionales basados únicamente
en palabras clave presentan limitaciones para interpretar contenido técnico complejo y no
estructurado, mientras que los enfoques basados en NLP permiten extraer relaciones,
conceptos y procedimientos de forma más contextual. Además, el estudio evidencia mejoras
en procesos de recuperación de información y comprensión de contenido técnico frente a
métodos convencionales.
En el contexto del sistema propuesto para la UNAB, estas capacidades serán utilizadas para
desarrollar un módulo de análisis automático de entregas. A medida que el estudiante cargue
avances del proyecto, el sistema realizará una revisión preliminar del contenido frente a
parámetros definidos para cada fase del proceso de grado. Con base en este análisis, se
generará retroalimentación orientativa relacionada con aspectos que cumplen los criterios
esperados y posibles elementos que requieren ajustes antes de la revisión formal del director.
No obstante, el propósito del sistema no es reemplazar el criterio académico humano ni
automatizar completamente la evaluación del proyecto. La literatura relacionada con
inteligencia artificial aplicada a educación también reconoce limitaciones importantes
asociadas a interpretación contextual, precisión de resultados y posibles sesgos en modelos de
lenguaje. Por esta razón, la funcionalidad propuesta tendrá un carácter complementario y de
apoyo, manteniendo la validación final bajo responsabilidad del director y la coordinación
académica.
Adicionalmente, el sistema incorporará un asistente conversacional integrado orientado a
brindar apoyo informativo durante las primeras etapas del proceso de grado. A través de una
interfaz de chat, el estudiante podrá consultar aspectos relacionados con el procedimiento
académico, requisitos de entrega, estructura documental y posibles líneas temáticas de
proyecto. Asimismo, el sistema podrá sugerir posibles directores de acuerdo con la
descripción general de la propuesta planteada por el estudiante, considerando áreas de
especialización, experiencia investigativa y afinidad temática de los docentes del programa.

Esta funcionalidad se apoyará en técnicas de procesamiento de lenguaje natural (NLP) y
búsqueda semántica, las cuales permiten interpretar consultas escritas en lenguaje natural y
generar respuestas contextualizadas a partir de información previamente estructurada
(Choudhary et al., 2024). El chatbot tendrá un carácter orientativo y de acompañamiento, por
lo que no sustituirá las decisiones académicas oficiales ni los procesos administrativos
definidos por la coordinación del programa.
Finalmente, es importante señalar que el proyecto no contempla el entrenamiento de modelos
de inteligencia artificial propios. En su lugar, se prevé integrar servicios y APIs existentes
basados en modelos de lenguaje, aprovechando capacidades ya desarrolladas para
procesamiento textual y análisis semántico. La selección específica de herramientas y
tecnologías será definida durante la fase de diseño detallado y levantamiento de requisitos del
sistema.
6.1.10. Experiencia de Usuario y Usabilidad
La experiencia de usuario (UX) se refiere al conjunto de percepciones que tiene una persona
al interactuar con un sistema digital. En el diseño de sistemas académicos, la usabilidad es un
factor clave: si la plataforma es difícil de usar, los actores simplemente seguirán utilizando el
correo electrónico, que les resulta más familiar.
Para el sistema de la UNAB, el diseño de la interfaz debe considerar que los usuarios tienen
perfiles distintos: un estudiante que está desarrollando su proyecto de grado, un director que
supervisa varios proyectos simultáneamente y un coordinador que necesita una visión general
del estado del programa. Cada uno necesita acceder a información diferente de forma rápida
y sin fricciones.
Por esta razón, el diseño de las interfaces del sistema se basará en principios de diseño
centrado en el usuario, con especial atención a la claridad de la navegación, la presentación
organizada de la información y la reducción de pasos innecesarios para completar las tareas
más frecuentes de cada rol.
6.2. Estado del Arte
El seguimiento de proyectos de grado es un problema que distintos grupos de investigación
han intentado resolver mediante el desarrollo de sistemas especializados. La revisión de estos
trabajos permitió identificar qué funcionalidades ya han sido exploradas, cuáles presentan
limitaciones y dónde existe una oportunidad real para el sistema que se propone en este
anteproyecto.
Un hallazgo común en la literatura revisada es que muchos programas universitarios aún
gestionan los proyectos de grado con herramientas fragmentadas: correo electrónico,
reuniones virtuales y carpetas compartidas sin estructura. Esta situación no es exclusiva de la

UNAB; es el punto de partida que justifica la mayoría de los sistemas descritos a
continuación (Retnowardhani & Suroso, 2019; Türkoğlu & Aslankoç, 2025 ).
6.2.1. Sistemas de gestión y centralización de proyectos académicos
El sistema PAMS (Reshmi et al., 2025; Arumugam et al., 2021) y la plataforma colaborativa
propuesta por Jayakumar et al. (2024), son los referentes más directos para el sistema de la
UNAB. Ambos proponen entornos integrados que conectan estudiantes, tutores y
coordinadores en un único espacio digital, facilitando la asignación de proyectos, el registro
de avances y la generación de reportes.
La principal ventaja de estos sistemas es que eliminan la fragmentación de herramientas y
mejoran la visibilidad del progreso de los proyectos (Ouyang, 2024). Sin embargo, ninguno
contempla funcionalidades de inteligencia artificial orientadas al proceso mismo del
estudiante: ni para orientarlo en la selección de director, ni para analizar el contenido de sus
entregas frente a los criterios de cada fase. El sistema propuesto para la UNAB busca cubrir
esa brecha con dos funcionalidades concretas de IA integradas directamente en el flujo del
proceso de grado.
6.2.2. Metodologías y marcos formales para el diseño y gestión de
proyectos
El sistema FYPMS (Ong et al., 2025) es un caso interesante porque introduce la Information
System Design Theory como marco estructural para el desarrollo de sistemas académicos. El
concepto de 'State of Completeness' que propone —es decir, validar que los requisitos estén
completos antes de avanzar al desarrollo— es un enfoque que se adoptará en este proyecto
como parte del proceso de levantamiento y validación de requisitos.
Otros trabajos revisados, como los de Aghileh et al. (2025) y Albayati & Aminbakhsh (2023),
destacan la importancia de modelos estructurados para la planificación y control de
proyectos. Si bien estos enfoques provienen de contextos de ingeniería más amplios, los
principios de seguimiento por hitos y control de entregables son perfectamente aplicables al
proceso de grado del programa.
6.2.3. Seguimiento del desarrollo y medición de productividad
Una categoría de sistemas que resulta particularmente relevante para este proyecto es la de
herramientas que monitorean no solo los entregables finales, sino el proceso real de trabajo.
BlueLogger (Silvestre et al., 2023) y el enfoque basado en repositorios de GitHub (Robbes &
Lanza, 2007) son ejemplos de esto: permiten capturar interacciones dentro del entorno de
desarrollo, registrar tiempo de actividad y detectar patrones como la procrastinación o la baja
productividad.
Aunque estos sistemas van más allá del alcance definido para el sistema de la UNAB en su
primera versión, la lógica subyacente de hacer seguimiento al proceso y no solo al resultado

es precisamente lo que inspira las funcionalidades de IA propuestas. La diferencia está en el
enfoque: en lugar de monitorear el IDE o los commits, el sistema analiza el contenido de los
documentos entregados en cada fase y apoya al estudiante desde el inicio del proceso con
orientación sobre la elección de director.
6.2.4. Analítica avanzada, motivación y apoyo a la toma de decisiones
Algunos sistemas revisados incorporan técnicas de analítica avanzada e inteligencia artificial
para transformar los datos del proceso en información útil para la toma de decisiones
(Almalki, 2025; Guitart & Conesa, 2016; Saputra et al., 2024). Estos enfoques van desde
modelos predictivos de riesgo hasta asignación inteligente de recursos y análisis de
desempeño histórico.
Project League (Kumar et al., 2023) añade una dimensión adicional: la gamificación.
Mediante rankings, hitos y evaluaciones continuas busca mantener la motivación de los
estudiantes en proyectos de larga duración. Este enfoque es innovador, aunque su efectividad
depende mucho del contexto cultural e institucional. Para el programa de la UNAB, los
elementos de gamificación podrían explorarse en versiones futuras del sistema, una vez
establecida la funcionalidad base.
6.2.5. Seguridad y arquitecturas modernas en sistemas académicos
El sistema FIPOS (Cahyanto et al., 2022) es el referente más claro en materia de seguridad
entre los trabajos revisados. Su implementación de autenticación OTP y el seguimiento de las
directrices OWASP demuestran que es posible incorporar buenas prácticas de seguridad sin
sacrificar la usabilidad de la plataforma.
En cuanto a arquitectura, varios de los sistemas revisados utilizan el stack MERN y
arquitecturas basadas en microservicios o APIs (Kumar et al., 2023; Reshmi et al., 2025; Li et
al., 2022), lo que les permite mayor escalabilidad e integración con herramientas externas.
Estas decisiones técnicas sirven como referente para la selección del stack tecnológico del
sistema de la UNAB, que también requiere una arquitectura que facilite el mantenimiento y la
incorporación de nuevas funcionalidades.
6.2.6. Análisis crítico y oportunidad de investigación
Después de revisar los sistemas anteriores, es posible identificar un patrón claro: la mayoría
se enfoca en la centralización y la trazabilidad básica del proceso —entregas,
comunicaciones, asignaciones—, pero pocos se adentran en el análisis del contenido real de
los documentos o en la medición de productividad más allá de los entregables finales.
Otras limitaciones recurrentes incluyen la escasa personalización según el contexto
institucional específico, la falta de métricas inteligentes de progreso y el uso todavía
incipiente de modelos predictivos en la gestión académica (Almalki, 2025; Silvestre et al.,
2023; Aghileh et al., 2025).

Esta brecha representa la oportunidad que justifica el desarrollo del sistema propuesto para la
UNAB. El sistema busca combinar las funcionalidades ya validadas en los trabajos anteriores
6.2.7. Tabla de Artículos
A continuación se presenta un resumen de los principales trabajos revisados, sus aportes y su
relación con el sistema propuesto:
Artículo Sistema / Aportes principales Impacto para el proyecto
Enfoque
Reshmi et al., 2025 PAMS Gestión centralizada, Base para sistema centralizado
reportes, integración de
actores
Ong et al., 2025 Design Theory Meta-requisitos, Base metodológica
trazabilidad
Kumar et al., 2023 Project League Hitos, ranking, stack Motivación y seguimiento
moderno
Cahyanto et al., FIPOS OTP, OWASP, seguridad Seguridad del sistema
2022
Jayakumar et al., Plataforma Colaboración y Coordinación multiusuario
2024 colaborativa dashboards
Silvestre et al., 2023 BlueLogger Métricas, hábitos, Seguimiento del proceso
monitoreo IDE
Robbes & Lanza, GitMonitor Seguimiento por commits Control automático del progreso
2007 y ramas
Almalki, 2025 AI Decision Soporte a decisiones con Analítica y toma de decisiones
Support IA, mitigación de riesgos,
asignación de recursos en
gestión ágil de proyectos
Retnowardhani & PMIS / WBS, Gantt, EVM Control estructurado de proyectos
Suroso, 2019 PMBOK
Kumar et al., 2023 MERN / Escalabilidad e Base tecnológica del sistema
Arquitectura integración
Varios Sistemas Gestión manual/correo Justificación del problema
tradicionales
6.3. Marco Normativo y Legal
El desarrollo e implementación de un sistema centralizado para el seguimiento y control de
proyectos de grado en el programa de Ingeniería de Sistemas de la UNAB implica el
tratamiento de datos personales de estudiantes y docentes, la creación de un producto de

software, el uso potencial de componentes de inteligencia artificial con licencias de terceros,
y su aplicación en un contexto educativo institucional. Por ello, el proyecto debe estar
alineado con el conjunto de normas nacionales e internacionales que regulan estas
dimensiones, garantizando su legalidad, seguridad y pertinencia ética.
6.3.1. Ley 1581 de 2012 – Protección de Datos Personales
Esta ley establece el régimen general de protección de datos personales en Colombia,
exigiendo que todo tratamiento de información personal cumpla principios de legalidad,
finalidad, libertad, veracidad, transparencia y seguridad (Congreso de la República de
Colombia, 2012). Dado que el sistema almacenará nombres, correos institucionales,
calificaciones, documentos académicos y registros de actividad de estudiantes y docentes de
la UNAB, es obligatorio que la plataforma implemente mecanismos de autenticación de
usuarios, control de acceso basado en roles, cifrado de datos en tránsito y en reposo, y que
cuente con una política de tratamiento de datos que los usuarios acepten explícitamente antes
de ingresar al sistema. El Decreto 1074 de 2015, que compila la regulación del sector
comercio e industria incluyendo las disposiciones reglamentarias de esta ley, complementa
los requisitos de registro y gestión del responsable del tratamiento (Ministerio de Comercio,
Industria y Turismo, 2015).
6.3.2. Ley 1266 de 2008 – Hábeas Data
Esta ley regula el manejo de la información contenida en bases de datos personales,
reconociendo el derecho de los titulares a conocer, actualizar y rectificar los datos que sobre
ellos reposen en cualquier base de datos (Congreso de la República de Colombia, 2008). Para
el sistema propuesto, esto implica que estudiantes y docentes deben poder consultar,
modificar y solicitar la eliminación de su información personal almacenada en la plataforma,
funcionalidad que debe estar contemplada desde el diseño del sistema y no incorporada como
un añadido posterior.
6.3.3. Ley 23 de 1982 y Ley 1915 de 2018 – Derechos de Autor
La Ley 23 de 1982 establece el régimen de derechos de autor en Colombia, protegiendo las
obras literarias, científicas y técnicas, categoría bajo la cual se enmarca el software
desarrollado en el marco del proyecto (Congreso de la República de Colombia, 1982). La Ley
1915 de 2018 moderniza este régimen incorporando disposiciones sobre entornos digitales y
obras en línea (Congreso de la República de Colombia, 2018). En la práctica, estas normas
implican que el código fuente del sistema y los documentos académicos cargados por los
estudiantes constituyen obras protegidas. El proyecto deberá definir explícitamente la licencia
bajo la cual se distribuirá el software desarrollado, y garantizar que el uso de bibliotecas o
modelos de terceros respete sus respectivas licencias, aspecto que deberá verificarse durante
la fase de selección tecnológica.

6.3.4. Ley 1341 de 2009 y Ley 1978 de 2019 – Acceso a las Tecnologías de
la Información
La Ley 1341 de 2009, modificada y actualizada por la Ley 1978 de 2019, establece los
principios y conceptos sobre la sociedad de la información y la organización de las
tecnologías de la información y las comunicaciones en Colombia (Congreso de la República
de Colombia, 2009, 2019). En el contexto del proyecto, esta normativa respalda el desarrollo
de soluciones tecnológicas orientadas a mejorar la calidad de los servicios educativos
mediante el uso de TIC, otorgando pertinencia institucional y social al sistema propuesto
dentro del marco de la transformación digital en la educación superior colombiana.
6.3.5. Norma ISO/IEC 25010 – Calidad del Software
Esta norma internacional define el modelo de calidad para productos de software,
estableciendo atributos como funcionalidad, confiabilidad, usabilidad, eficiencia,
mantenibilidad, portabilidad y seguridad (International Organization for Standardization,
2011). Aunque su cumplimiento no es legalmente obligatorio en Colombia, constituye el
estándar de referencia para el diseño y evaluación del sistema propuesto, particularmente en
relación con el tercer objetivo específico del proyecto, que contempla la evaluación del
sistema mediante pruebas funcionales y escenarios de uso. Los atributos de usabilidad y
confiabilidad serán prioritarios dado el perfil de los usuarios finales y la criticidad del proceso
académico que el sistema soporta.
6.3.6. Norma ISO/IEC 27001 – Seguridad de la Información
Esta norma establece los requisitos para implementar, mantener y mejorar un sistema de
gestión de seguridad de la información, orientando las decisiones de diseño relacionadas con
la confidencialidad, integridad y disponibilidad de los datos gestionados (International
Organization for Standardization, 2013). Aplicada al presente proyecto, sus principios
guiarán la implementación de controles de seguridad en la arquitectura del sistema,
incluyendo la gestión de credenciales, el registro de auditoría de acciones realizadas sobre los
documentos y la protección de la información académica almacenada. Si bien la certificación
formal bajo esta norma excede el alcance del proyecto de grado, su adopción como referente
de diseño fortalece la calidad y confiabilidad de la solución.
6.3.7. Política de Propiedad Intelectual de la Universidad Autónoma de
Bucaramanga
El sistema contempla la integración de componentes de inteligencia artificial para el análisis
semántico de documentos y el monitoreo del avance de los proyectos. Dado que la selección
específica de herramientas, modelos y APIs se encuentra aún en fase de evaluación técnica, el
marco normativo aplicable a esta dimensión deberá consolidarse durante la etapa de diseño
del sistema. Sin embargo, desde esta instancia se establece que todo componente de terceros
que sea incorporado quedará sujeto a sus respectivas licencias de uso. Estas pueden ser de
código abierto —con variantes como MIT, Apache 2.0 o GPL, cada una con condiciones

distintas respecto a modificación y redistribución— o de naturaleza comercial, en cuyo caso
sus términos de servicio deberán evaluarse en cuanto a compatibilidad con la Ley 1581 de
2012 (Congreso de la República de Colombia, 2012) y la Resolución N° 403 del 27 de julio
de 2013 de la UNAB (Universidad Autónoma de Bucaramanga, 2013), especialmente cuando
impliquen transferencia de datos personales de usuarios colombianos a servidores ubicados
fuera del territorio nacional. Dicha verificación constituye un requisito previo e ineludible a
la implementación de cualquier componente de esta naturaleza, conforme al Principio de
Seguridad y al Artículo 15 de la mencionada Resolución, que regula la utilización y
transferencia internacional de datos personales.
6.3.8. Políticas Institucionales de la Universidad Autónoma de
Bucaramanga
Como proyecto desarrollado en el marco del programa de Ingeniería de Sistemas de la UNAB
y orientado a su implementación dentro de la misma institución, el sistema se enmarca en las
políticas institucionales de protección de datos, seguridad informática y propiedad intelectual
de la universidad. En particular, la Política de Propiedad Intelectual de la UNAB, aprobada
mediante Acta de Junta Directiva N° 613 del 25 de mayo de 2021, regula en su Artículo 5 las
condiciones bajo las cuales los productos intelectuales generados por estudiantes en el curso
de proyectos de investigación pueden ser de titularidad de la institución, especialmente
cuando se hace uso sustancial de sus recursos o cuando el desarrollo forma parte de un
proyecto de investigación institucional (Universidad Autónoma de Bucaramanga, 2021). La
titularidad del presente sistema ha sido definida y formalizada con la coordinación académica
correspondiente, en cumplimiento de las disposiciones establecidas en dicha Política, por lo
que este aspecto no representa una contingencia pendiente para el desarrollo del proyecto.
6.4. Consideraciones éticas
El desarrollo del sistema centralizado involucra el tratamiento de datos personales de
estudiantes y docentes, el procesamiento automatizado de documentos académicos mediante
técnicas de inteligencia artificial, y su aplicación directa en un contexto educativo
institucional. Por ello, el proyecto define desde esta etapa un conjunto de medidas éticas
concretas que orientan cada fase de su desarrollo.
En cuanto a la privacidad y el tratamiento de datos, el sistema será diseñado bajo el principio
de privacidad desde el diseño, lo que implica que la recolección de información personal se
limitará a lo estrictamente necesario para el funcionamiento del sistema. Los datos serán
almacenados de forma cifrada y el acceso a la información estará restringido según el rol de
cada usuario. Durante la fase de pruebas no se utilizarán datos reales de estudiantes ni
docentes; los escenarios de validación serán construidos con información simulada.
En relación con el uso de inteligencia artificial, tanto las sugerencias del asistente
conversacional como la retroalimentación generada por el módulo de análisis de entregas
tendrán carácter orientativo. El asistente no asigna directores ni condiciona la decisión de la

coordinación; solo ofrece información de apoyo al estudiante. El módulo de análisis no
califica ni determina la aprobación de una entrega; su función es complementar la revisión
del director con información estructurada sobre el contenido del avance. La autonomía del
director, del estudiante y de la coordinación sobre el proceso formativo se preserva en todo
momento.
Respecto a la transparencia, los usuarios serán informados sobre qué datos procesa el sistema,
con qué finalidad y bajo qué criterios genera sus análisis y alertas, de modo que el
funcionamiento del sistema sea comprensible para todos los actores del proceso.
Este proyecto se clasifica como de riesgo mínimo, dado que no involucra menores de edad,
no interviene sobre poblaciones vulnerables y no toma decisiones autónomas de carácter
vinculante sobre los usuarios. En caso de una implementación institucional ampliada, se
deberán obtener los consentimientos informados correspondientes y someter el proyecto a
revisión por parte del comité de ética de la UNAB.
7. Diseño Metodológico
El diseño metodológico define la ruta estructurada que seguirá el Sistema Centralizado para
el Seguimiento y Control de Proyectos de Grado del Programa de Ingeniería de Sistemas de
la UNAB, desde su formulación hasta su validación con usuarios reales. El proyecto se
clasifica como un desarrollo tecnológico aplicado que busca alcanzar un nivel de madurez
tecnológica TRL 5. La metodología articula tres marcos complementarios: el ciclo PHVA
como eje de mejora continua, las fases del PMBOK como estructura de gestión del proyecto,
y una metodología de desarrollo híbrida compuesta por ICONIX para el diseño e incremental
para la implementación como enfoque técnico de construcción. El proyecto se organiza en
cuatro fases, cada una alineada directamente con uno de los cuatro objetivos específicos.
7.1. Aplicación del ciclo PHVA
El ciclo PHVA (Planear – Hacer – Verificar – Actuar) estructura la lógica de mejora continua
del proyecto. Cada fase se inscribe dentro de una etapa del ciclo, garantizando que ninguna
decisión técnica se tome sin diagnóstico previo ni retroalimentación posterior.
Etapas PHVA Actividades Fase del OE asociado Entregable
principales proyecto
Planear Diagnóstico del Fase 1 - OE 1 Documento
proceso, Diagnostico ERS + acta de
recolección de validación
información,
análisis de
actores,
especificación

de
requerimientos.
| Hacer  | Diseño de        | Fase 2 - Diseño  | OE2 / OE3  | Documento de  |
| ------ | ---------------- | ---------------- | ---------- | ------------- |
|        | arquitectura,    | Fase 3 -         |            | diseño +      |
|        | modelo de datos  | Implementacion   |            | Sistema web   |
|        | e interfaces.    |                  |            | funcional     |
Implementación
de módulos e
integración del
componente de
IA.
| Verificar  | Pruebas          | Fase 4 -    | OE4  | Informe de    |
| ---------- | ---------------- | ----------- | ---- | ------------- |
|            | funcionales por  | Evaluacion  |      | evaluación y  |
|            | módulo, prueba   |             |      | validación    |
piloto con
usuarios reales,
instrumento
SUS.
| Actuar  | Corrección de  | Cierre del  |     | Informe final  |
| ------- | -------------- | ----------- | --- | -------------- |
|         | hallazgos,     | proyecto    |     | con lecciones  |
|         | documentación  |             |     | aprendidas     |
de ajustes,
cierre y entrega
formal.

| 7.2.  Adaptación de fases PMBOK  |     |     |     |     |
| -------------------------------- | --- | --- | --- | --- |
La gestión del proyecto sigue las cinco fases del PMBOK adaptadas al cronograma de 142
días hábiles (122 productivos + 20 de holgura distribuidos estratégicamente entre las cuatro
fases).
●  Inicio:
Definición del alcance, objetivos y entregables. Identificación formal de actores del
proceso y asignación de roles dentro del equipo de desarrollo.
●  Planificación:
Elaboración  del  cronograma  de  142  días  hábiles  distribuidos  en  cuatro  fases.
Definición de la EDT con actividades, responsables y entregables. Análisis de riesgos
y planes de contingencia.
●  Ejecución:

Desarrollo técnico del sistema siguiendo la metodología híbrida
ICONIX–incremental. Implementación secuencial de los módulos funcionales en
cinco incrementos.
● Monitoreo y Control:
Seguimiento semanal frente al cronograma. Revisiones de progreso con el director al
cierre de cada fase. Control de calidad mediante pruebas internas al finalizar cada
incremento.
● Cierre:
Entrega del sistema validado, documentación técnica completa, informe de resultados
de la prueba piloto y sustentación formal ante el jurado evaluador.
7.3. Fases del proyecto
FASE 1 Diagnóstico
OE1: Diagnosticar el proceso actual de gestión de proyectos de grado del programa de
Ingeniería de Sistemas de la UNAB mediante la recolección y análisis de información con los
actores involucrados, con el fin de especificar los requerimientos funcionales y no
funcionales que debe satisfacer el sistema centralizado.
Actividades:
1. Revisión y análisis del proceso actual de gestión de proyectos de grado en la UNAB
mediante entrevistas semiestructuradas con la coordinación académica y mínimo dos
directores de proyecto.
2. Identificación y documentación de los actores del sistema: estudiante, director de
proyecto y coordinación académica.
3. Levantamiento de requerimientos funcionales por actor mediante la técnica de casos
de uso de alto nivel.
4. Levantamiento de requerimientos no funcionales del sistema (seguridad, usabilidad y
rendimiento).
5. Modelado del sistema: elaboración del diagrama de casos de uso general y diagrama
de contexto.
6. Validación de los requerimientos levantados con el director del proyecto de grado.
Entregable OE1: Documento de Especificación de Requisitos del Sistema (ERS) que
incluya: lista de requerimientos funcionales y no funcionales priorizados, diagrama de casos
de uso general, diagrama de contexto del sistema y acta de validación firmada con el director.

FASE 2 Diseño
OE2: Diseñar la arquitectura y los componentes del sistema web centralizado mediante el
modelado de la estructura técnica, el modelo de datos y las interfaces de usuario, con el fin de
establecer las bases que guíen el proceso de implementación.
Actividades:
1. Diseño de la arquitectura del sistema en capas: frontend, backend y base de datos.
2. Diseño del modelo de datos: diagrama entidad-relación y modelo relacional.
3. Diseño de interfaces de usuario: wireframes por actor (estudiante, director,
coordinación).
4. Diseño del módulo de análisis semántico: definición del enfoque de procesamiento de
lenguaje natural y selección de librería o componente.
5. Definición y justificación técnica del stack tecnológico del sistema.
6. Elaboración de diagramas de robustez por caso de uso.
7. Elaboración de diagramas de secuencia y actualización del diagrama de clases.
Entregable OE2: Documento de Diseño del Sistema que incluya: diagrama de arquitectura
en capas, diagrama entidad-relación y modelo relacional, wireframes de interfaces por actor,
diagramas de robustez, secuencia y clases, y justificación técnica del stack tecnológico
seleccionado.
FASE 3 Implementación
OE3: Implementar el sistema web centralizado mediante el desarrollo de sus módulos
funcionales e integración de componentes de inteligencia artificial, con el fin de construir una
solución que apoye el seguimiento y control de las fases del proyecto de grado.
Actividades:
1. Configuración del entorno de desarrollo y repositorio de código fuente.
2. Desarrollo del módulo de autenticación y gestión de roles.
3. Desarrollo del módulo de gestión documental (carga, versionado y consulta de
entregables).
4. Desarrollo del módulo de seguimiento por fases (anteproyecto, desarrollo, evaluación
y presentación final)
5. Desarrollo del módulo de asignación de directores y evaluadores.
6. Desarrollo del módulo de comunicación entre actores del proceso.
7. Integración de las funcionalidades de inteligencia artificial: asistente conversacional
para orientación en la selección de director, y módulo de análisis automático de
entregas con retroalimentación por fase.
8. Integración general de todos los módulos y pruebas internas por módulo.

Entregable OE3: Sistema web centralizado funcional que incluya: código fuente
documentado en repositorio, manual técnico de arquitectura, diccionario de datos, diagramas
UML de clases, secuencia y despliegue, y sistema desplegado en entorno de pruebas con
todos los módulos operativos, incluyendo el asistente conversacional de selección de director
y el módulo de análisis automático de entregas.
Como criterio de validación específico para los componentes de inteligencia artificial, se
establecen los siguientes umbrales mínimos de aceptación: (a) el módulo de análisis
automático de entregas deberá generar retroalimentación coherente con los criterios de la fase
correspondiente en al menos el 70% de los casos, validado mediante revisión directa de un
director de proyecto; (b) el asistente conversacional deberá sugerir al menos un director
pertinente según la propuesta descrita por el estudiante en al menos el 80% de las consultas
de prueba, evaluado por la coordinación académica. Estos umbrales serán verificados durante
la prueba piloto de la Fase 4 y documentados en el informe de evaluación.
FASE 4 Evaluación
OE4: Evaluar el funcionamiento del sistema desarrollado para verificar su capacidad de
apoyar el seguimiento y control de los proyectos de grado, mediante pruebas funcionales y
escenarios de uso que permitan analizar su desempeño y utilidad en el contexto académico.
Actividades:
1. Diseño del plan de pruebas funcionales por módulo con casos de prueba construidos a
partir de cada requerimiento del ERS.
2. Ejecución de pruebas funcionales internas con todos los casos de prueba
documentados.
3. Diseño de los escenarios de prueba piloto con usuarios reales.
4. Ejecución de la prueba piloto con el grupo selecto: directores de proyecto con sus
estudiantes y la coordinación académica del programa.
5. Aplicación del instrumento de evaluación de usabilidad SUS (System Usability
Scale).
6. Recolección, análisis e interpretación de los resultados obtenidos.
7. Documentación de hallazgos, limitaciones y recomendaciones para versiones futuras
del sistema.
Entregable OE4: Informe de Evaluación y Validación del Sistema que incluya: plan de
pruebas ejecutado con resultados por caso de prueba, resultados de la prueba piloto con
usuarios reales, análisis de usabilidad mediante instrumento SUS con puntajes por perfil de
usuario, y conclusiones sobre el grado de cumplimiento de los requerimientos especificados.

7.4.  Nivel de madurez tecnológica (TRL)
El proyecto apunta a alcanzar TRL 5 al finalizar la Fase 4. La progresión por niveles se
distribuye así:

| TRL    | Fase                  | Descripción            | Evidencia clave       |
| ------ | --------------------- | ---------------------- | --------------------- |
| TRL 2  | Fase 1 - Diagnostico  | Formulación del        | Documento ERS         |
|        |                       | concepto con base      | validado con actores  |
|        |                       | en evidencia real del  | del proceso.          |
proceso académico.
| TRL 3  | Fase 2 - Diseño  | Prueba de concepto:  | Documento de    |
| ------ | ---------------- | -------------------- | --------------- |
|        |                  | arquitectura         | diseño técnico  |
|        |                  | definida, modelo de  | completo con    |
|        |                  | datos y wireframes   | diagramas UML.  |
validados.
| TRL 4  | Fase 3 -        | Prototipo funcional  | Sistema web       |
| ------ | --------------- | -------------------- | ----------------- |
|        | implementacion  | con todos los        | funcional con     |
|        |                 | módulos integrados,  | pruebas internas  |
|        |                 | validado en entorno  | aprobadas.        |
controlado.
TRL 5  Fase 4 - Evaluacion  Sistema validado en  Informe de prueba
|     |     | entorno académico     | piloto con resultados  |
| --- | --- | --------------------- | ---------------------- |
|     |     | real con usuarios de  | SUS y pruebas          |
|     |     | la UNAB.              | funcionales.           |

7.5.  Recursos y Presupuesto
Aunque el proyecto se apoya en tecnologías de código abierto, a continuación se presenta una
estimación de los recursos necesarios para su desarrollo y validación. Los costos marcados
como  "absorbido"  corresponden  a  recursos  aportados  por  los  integrantes  del  equipo  o
disponibles a través de planes gratuitos o académicos.

| Categoría  | Recurso  | Descripción  | Costo estimado  |
| ---------- | -------- | ------------ | --------------- |
(COP)

Infraestructura  Servidor  /  Despliegue  del  sistema  en  AWS  $80.000  –
|     | Hosting  | (EC2 o servicio equivalente según  |     |           | $200.000/mes  |     |
| --- | -------- | ---------------------------------- | --- | --------- | ------------- | --- |
|     |          | requerimientos                     | de  | la  fase  | de            |     |
pruebas)
APIs de IA  Servicio  de  Uso  de  API  para  asistente  $0  –  $100.000
modelo  de  conversacional  y  análisis  de  (estimado  para
lenguaje  entregas. Se evaluará el uso de la  fase de pruebas)
|     |     | API  de      | OpenAI,   | con  la  que  | la  |     |
| --- | --- | ------------ | --------- | ------------- | --- | --- |
|     |     | universidad  | mantiene  | convenio,     | o   |     |
una alternativa compatible con las
|     |     | tecnologías  | de  Anthropic  |            | según   |     |
| --- | --- | ------------ | -------------- | ---------- | ------- | --- |
|     |     | criterios    | técnicos       | definidos  | en  la  |     |
Fase 2
Almacenamient Base de datos  Base de datos relacional desplegada  Por definir
| o   |     | en el servidor AWS. Se contempla  |                |     |     |     |
| --- | --- | --------------------------------- | -------------- | --- | --- | --- |
|     |     | una  copia                        | de  seguridad  | en  | un  |     |
servicio adicional (por definir según
disponibilidad institucional)
Tiempo hombre  Desarrollador  60 días × 4 horas/día = 240 horas ×  $1.680.000
|     | 1   | $7.000/hora = $1.680.000  |     |     | (absorbido  | por  |
| --- | --- | ------------------------- | --- | --- | ----------- | ---- |
|     |     |                           |     |     | el  equipo  | de   |
desarrollo)
Tiempo hombre  Desarrollador  60 días × 4 horas/día = 240 horas ×  $1.680.000
|     | 2   | $7.000/hora = $1.680.000  |     |     | (absorbido  | por  |
| --- | --- | ------------------------- | --- | --- | ----------- | ---- |
|     |     |                           |     |     | el  equipo  | de   |
desarrollo)
Herramientas de  IDEs,  control  VS  Code,  GitHub,  Figma  (planes  $0
| desarrollo  | de  versiones,  | gratuitos)  |     |     |     |     |
| ----------- | --------------- | ----------- | --- | --- | --- | --- |
diseño

| Total estimado  |     |     | $3.440.000  | –   |
| --------------- | --- | --- | ----------- | --- |
$3.710.000
| Adsorbido       | por    |     | $80.000        | –   |
| --------------- | ------ | --- | -------------- | --- |
| la universidad  |        |     | $350.000/mes   |     |
| Adsorbido       | por    |     | $3.360.000     |     |
| el  equipo      | de     |     |                |     |
desarrollo
El costo de tiempo hombre representa el rubro más significativo del proyecto, aunque es
absorbido directamente por el equipo de desarrollo. Los costos de infraestructura y APIs son
variables según el plan seleccionado y el volumen de uso durante la fase de pruebas. El valor
del almacenamiento secundario quedará definido una vez se confirme la disponibilidad de
recursos institucionales.
| 7.6.  | Metodología de desarrollo  |     |     |     |
| ----- | -------------------------- | --- | --- | --- |
Se adopta una metodología híbrida que combina ICONIX en la Fase 2 (Diseño) y desarrollo
incremental en la Fase 3 (Implementación). Esta combinación responde a las características
del proyecto: tres tipos de actores con flujos distintos que exigen un modelado riguroso antes
de codificar, y una implementación compuesta por módulos independientes que se construyen
y validan de forma progresiva.
7.6.1.  ICONIX Aplicado en la Fase 2 (Diseño)
ICONIX es un proceso liviano orientado a objetos y basado en casos de uso que produce los
artefactos de diseño necesarios para guiar la implementación con trazabilidad directa desde
los requerimientos hasta el código. Sus cuatro pasos se aplican en la Fase 2:

•       Definición de casos de uso:
A  partir  de  los  requerimientos  del  ERS,  se  elaboran  los  casos  de  uso  de  cada  actor
(estudiante, director y coordinación), describiendo flujos principales y alternativos.
•       Análisis de robustez:
Se construyen diagramas de robustez por caso de uso, identificando objetos de frontera,
control y entidad. Permite detectar inconsistencias antes de codificar.
•       Diseño de secuencia:

Se  elaboran  diagramas  de  secuencia  que  detallan  la interacción entre componentes del
sistema, asignando comportamiento a las clases identificadas.
•       Diseño de clases:
Se actualiza el diagrama de clases con atributos y métodos definidos durante el análisis de
robustez y los diagramas de secuencia, dejando el modelo listo para la Fase 3.

Con el fin de evitar la parálisis por análisis, se establece un límite de dos ciclos de revisión
por artefacto de diseño (diagrama de robustez, secuencia o clases) antes de cerrar la Fase 2 e
iniciar la implementación incremental. Si tras dos ciclos persisten observaciones menores,
estas se registran como deuda técnica y se resuelven durante el primer incremento de la Fase
3, sin bloquear el avance del proyecto.

7.6.2.  Desarrollo Incremental Aplicado en la Fase 3 (Implementación)
La implementación se organiza en cinco incrementos secuenciales. Cada incremento agrega
funcionalidad al sistema y es internamente probado antes de avanzar al siguiente, lo que
reduce el riesgo de fallos tardíos y permite verificar la integración de forma progresiva.

| Incremento  | Módulo  | Funcionalidades  | Criterio de  |
| ----------- | ------- | ---------------- | ------------ |
|             |         | implementadas    | aceptación   |
1  Autenticación y gestión de  Registro, inicio de  Los tres perfiles
|     | roles  | sesión seguro y    | acceden únicamente  |
| --- | ------ | ------------------ | ------------------- |
|     |        | control de acceso  | a sus funciones     |
|     |        | diferenciado por   | asignadas.          |
perfil (estudiante,
director,
coordinación).
2  Gestión documental  Carga, versionado y  Un estudiante carga
|     |                          | consulta de           | un documento y el     |
| --- | ------------------------ | --------------------- | --------------------- |
|     |                          | entregables por fase  | director lo consulta  |
|     |                          | del proyecto de       | y aprueba             |
|     |                          | grado.                | correctamente.        |
| 3   | Seguimiento por fases y  | Monitoreo del         | La coordinación       |
|     | asignación de roles      | avance por fases      | asigna y reasigna     |
|     |                          | (anteproyecto,        | directores sin        |
|     |                          | desarrollo,           | conflictos de datos.  |
evaluación,
presentación).

Asignación de
directores y
evaluadores por la
coordinación.
| 4   | Comunicación entre  | Canal de mensajería  | Los mensajes se     |
| --- | ------------------- | -------------------- | ------------------- |
|     | actores             | interna entre        | entregan entre      |
|     |                     | estudiantes y        | perfiles y las      |
|     |                     | directores.          | notificaciones se   |
|     |                     | Notificaciones de    | generan en los      |
|     |                     | eventos relevantes   | eventos definidos.  |
del proceso.
| 5   | Funcionalidades de       | Asistente              | El asistente sugiere   |
| --- | ------------------------ | ---------------------- | ---------------------- |
|     | inteligencia artificial  | conversacional para    | directores             |
|     |                          | orientación en la      | pertinentes según la   |
|     |                          | selección de director  | propuesta descrita     |
|     |                          | de proyecto según      | por el estudiante. El  |
|     |                          | perfil docente.        | módulo de análisis     |
|     |                          | Módulo de análisis     | genera                 |
|     |                          | automático de          | retroalimentación      |
|     |                          | entregas que evalúa    | coherente con los      |
|     |                          | cada avance frente a   | criterios de la fase   |
|     |                          | los criterios de la    | en al menos el 70%     |
|     |                          | fase correspondiente   | de los casos           |
|     |                          | y genera               | validados por          |
|     |                          | retroalimentación      | directores.            |
estructurada.

| 7.7.  | Diseño de Ingeniería  |     |     |
| ----- | --------------------- | --- | --- |

| Criterio  | A. Adaptar Moodle  | B. Plataforma  | C. Sistema web  |
| --------- | ------------------ | -------------- | --------------- |
genérica (Trello /  especializado
Asana)  (propuesta)
Adaptación  al  Baja.  Diseñado para  Baja.  Sin  soporte  Alta.  Diseñado
proceso de grado  cursos,  no  para  para  fases  específicamente  para
|     | proyectos        | académicas    | ni  el flujo del proyecto  |
| --- | ---------------- | ------------- | -------------------------- |
|     | longitudinales.  | aprobaciones  | de grado UNAB.             |
formales.

Trazabilidad Parcial. Sin control Limitada. Sin Completa.
documental de versiones ni gestión documental Versionado, historial
historial de formal. de entregas y
observaciones. observaciones.
Análisis No disponible. No disponible. Integrado como
semántico con IA módulo propio dentro
del sistema.
Costo de Medio. Requiere Bajo-medio. Costo Bajo-medio.
implementación plugins adicionales. por usuario en Tecnologías de
versiones avanzadas. código abierto sin
licencias,
implementación en la
nube.
7.8. Validación de Resultados
La validación del sistema se realiza en dos niveles complementarios durante la Fase 4 del
proyecto:
7.8.1. Pruebas Funcionales Internas
Se elabora un plan de pruebas con casos de prueba construidos a partir de cada requerimiento
funcional del ERS. Para cada caso se define: condición de entrada, procedimiento, resultado
esperado y resultado obtenido. El 100% de los requerimientos prioritarios deben aprobarse
antes de ejecutar la prueba piloto.
7.8.2. Prueba Piloto con Usuarios Reales
Se diseñan escenarios que replican el flujo real del proceso de grado: un estudiante carga un
entregable, el director lo revisa y genera observaciones, y la coordinación consulta el estado
general del proyecto. La prueba se ejecuta con un grupo selecto de directores con sus
estudiantes y la coordinación del programa de Ingeniería de Sistemas de la UNAB.
7.8.3. Evaluación de Usabilidad Instrumento SUS
Al finalizar la prueba piloto, se aplica el instrumento System Usability Scale (SUS) a todos
los participantes. El SUS consta de 10 ítems en escala Likert de 1 a 5, con puntaje final entre

0 y 100. Un puntaje igual o superior a 70 puntos se considera aceptable (Bangor et al., 2008).
Los resultados se analizan por perfil de usuario para identificar áreas de mejora diferenciadas.
7.8.4. Documentación de Hallazgos
Los hallazgos de ambas etapas se documentan en el Informe de Evaluación, que incluye
resultados cuantitativos de las pruebas funcionales, puntajes SUS por perfil, hallazgos
cualitativos de la prueba piloto, limitaciones identificadas y recomendaciones para versiones
futuras. Este documento constituye el entregable final del proyecto de grado.
8. Resultados esperados
Al finalizar el proyecto se espera contar con un sistema web funcional compuesto por cinco
módulos operativos: autenticación con control de acceso diferenciado por perfil, gestión
documental con versionado de entregas, seguimiento estructurado por fases del proyecto de
grado, asignación de directores y evaluadores por parte de la coordinación, y comunicación
interna entre los actores del proceso. Adicionalmente, el sistema integrará dos
funcionalidades de inteligencia artificial: un asistente conversacional que permite al
estudiante describir su propuesta de proyecto y recibir orientación sobre qué docentes del
programa podrían ser directores adecuados según su perfil académico y las asignaturas que
imparten, y un módulo de análisis automático de entregas que evalúa cada avance subido por
el estudiante frente a los criterios definidos para esa fase del proceso de grado, generando
retroalimentación estructurada de carácter orientativo para el estudiante y el director.
Desde el punto de vista de madurez tecnológica, el proyecto busca alcanzar un nivel TRL 5 al
cierre de la Fase 4. Esta progresión parte de TRL 2 en la fase de diagnóstico, donde se
formula el concepto con base en evidencia real del proceso académico; avanza a TRL 3 con
la definición de la arquitectura, el modelo de datos y los wireframes validados; alcanza TRL
4 con el prototipo funcional integrado y probado en entorno controlado; y llega a TRL 5 con
la validación del sistema en un entorno académico real con usuarios de la UNAB. Este nivel
implica que todos los módulos estarán integrados, operativos y sometidos a pruebas con
usuarios reales en condiciones similares a las de su aplicación definitiva.
La validación se realizará en dos etapas complementarias: primero, pruebas funcionales
construidas a partir de cada requerimiento del ERS, con criterio de aprobación del 100% de
los casos prioritarios; segundo, una prueba piloto con un grupo selecto de directores, sus
estudiantes y la coordinación del programa, al término de la cual se aplicará el instrumento
SUS con umbral de aceptabilidad de 70 puntos sobre 100.
Con el fin de hacer medible el impacto sobre la gestión administrativa, se define como
indicador clave de desempeño (KPI) la reducción del tiempo dedicado por la coordinación al
proceso de asignación de directores y al seguimiento del estado de los proyectos activos. Este
indicador se medirá comparando el tiempo promedio invertido en estas tareas antes y después
de la implementación del sistema, mediante un instrumento de registro aplicado durante la

prueba piloto. Se proyecta una reducción de al menos el 40% en el tiempo dedicado a dichas
tareas, tomando como línea base el tiempo reportado por la coordinación durante las
entrevistas de la Fase 1. Los resultados obtenidos serán documentados en el informe de
evaluación final.
En cuanto al impacto sobre el proceso, se proyecta que la coordinación reduzca el tiempo
dedicado a tareas como el registro de asignaciones y el seguimiento del estado de los
proyectos, al contar con una vista centralizada y actualizada. Los directores dispondrán de un
historial completo de entregas y observaciones por proyecto, sin depender del correo
electrónico ni de canales informales. Los estudiantes tendrán acceso a los estados de
aprobación de cada entrega y las observaciones asociadas dentro de una misma plataforma.
El producto final incluirá código fuente documentado, manual técnico, diccionario de datos y
diagramas UML, elementos que permiten su mantenimiento y eventual extensión a otros
programas de la facultad. No se garantiza que el sistema resuelva todas las dificultades del
proceso actual, pero sí que provee una base estructurada para gestionarlo de forma más
organizada y trazable que los mecanismos hoy en uso.
9. Referencias Bibliográficas
Aghileh, M., Tereso, A., Alvelos, F., & Monteiro Lopes, M. O. (2025). Multi-project
scheduling with uncertainty and resource flexibility: A narrative review and
exploration of future landscapes. Algorithms, 18(6), 314.
https://doi.org/10.3390/a18060314
Albayati, N. H. F., & Aminbakhsh, S. (2023). Resource allocation capabilities of
commercial project management software packages for resource leveling and
resource constrained project scheduling problems: A comparative study.
Journal of Construction Engineering, Management & Innovation, 6(2),
104–123. https://doi.org/10.31462/jcemi.2023.02104123
Almalki, S. S. (2025). AI-driven decision support systems in agile software project
management: Enhancing risk mitigation and resource allocation. Systems, 13(3),
208. https://doi.org/10.3390/systems13030208
Arumugam, V., Manek, R., Singh, P., Sayyad, S., & Padhiyar, K. (2021). Academic
project information management system. 2021 Asian Conference on Innovation
in Technology (ASIANCON).
https://doi.org/10.1109/ASIANCON51346.2021.9544565
Bangor, A., Kortum, P. T., & Miller, J. T. (2008). An empirical evaluation of the System
Usability Scale. International Journal of Human-Computer Interaction, 24(6),
574–594. https://doi.org/10.1080/10447310802205776

Cahyanto, M. I., Hadiprakoso, R. B., Setiawan, H., & Paramita, N. (2022).
Development of final year project system (FIPOS) based on website with
one-time password. Proceeding - IEEE 8th Information Technology
International Seminar, ITIS 2022, 178–183.
https://doi.org/10.1109/ITIS57155.2022.10009967
Choudhary, S., Jaiswal, H. K., Rajeevan, A. S., Mukherjee, P. D., & Hudnurkar, S.
(2024). Semantic Search of Educational Texts. En 2024 International
Conference on Intelligent Systems and Advanced Applications (ICISAA). IEEE.
https://doi.org/10.1109/ICISAA62596.2024.10602158
Congreso de la República de Colombia. (1982, 28 de enero). Ley 23 de 1982, sobre
derechos de autor. Diario Oficial N° 35.949.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=3431
Congreso de la República de Colombia. (2008, 31 de diciembre). Ley 1266 de 2008,
por la cual se dictan las disposiciones generales del hábeas data y se regula el
manejo de la información contenida en bases de datos personales. Diario Oficial
N° 47.219.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=34488
Congreso de la República de Colombia. (2009, 30 de julio). Ley 1341 de 2009, por la
cual se definen principios y conceptos sobre la sociedad de la información y la
organización de las tecnologías de la información y las comunicaciones. Diario
Oficial N° 47.426.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=36913
Congreso de la República de Colombia. (2012, 17 de octubre). Ley 1581 de 2012, por
la cual se dictan disposiciones generales para la protección de datos personales.
Diario Oficial N° 48.587.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981
Congreso de la República de Colombia. (2018, 2 de agosto). Ley 1915 de 2018, por la
cual se modifica la Ley 23 de 1982 y se establecen otras disposiciones en
materia de derecho de autor y derechos conexos. Diario Oficial N° 50.673.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=87426
Congreso de la República de Colombia. (2019, 25 de julio). Ley 1978 de 2019, por la
cual se moderniza el sector de las tecnologías de la información y las
comunicaciones. Diario Oficial N° 51.025.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=96996
Guitart, I., & Conesa, J. (2016). Evaluation of analytic projects in the context of higher
education. 2016 10th International Conference on Complex, Intelligent, and
Software Intensive Systems. https://doi.org/10.1109/CISIS.2016.52

International Organization for Standardization. (2011). ISO/IEC 25010:2011 —
Systems and software engineering: Systems and software quality requirements
and evaluation (SQuaRE): System and software quality models.
https://www.iso.org/standard/35733.html
International Organization for Standardization. (2013). ISO/IEC 27001:2013 —
Information technology: Security techniques: Information security management
systems: Requirements. https://www.iso.org/standard/54534.html
Jayakumar, N., Parvez Ahamed, J., Rubin Nivas, N. T., & Santhosh Kumar, J. V.
(2024). Cutting-edge integrated project management solution for academic
consortia. 2nd International Conference on Artificial Intelligence and Machine
Learning Applications: Healthcare and Internet of Things, AIMLA 2024.
https://doi.org/10.1109/AIMLA59606.2024.10531589
Kumar, S., Umrao, S., Gupta, H., & Saxena, K. (2023). Project management and
evaluation system using Node JS. 2023 3rd International Conference on
Advance Computing and Innovative Technologies in Engineering, ICACITE
2023, 567–571. https://doi.org/10.1109/ICACITE57410.2023.10183175
Li, C., Zhang, S. Y., Na, J. S., & Yue, L. (2022). Design and implementation of project
management system based on Vue3.0 framework. 2022 2nd International
Conference on Algorithms, High Performance Computing and Artificial
Intelligence (AHPCAI). https://doi.org/10.1109/AHPCAI57455.2022.10087744
Ministerio de Comercio, Industria y Turismo. (2015, 26 de mayo). Decreto 1074 de
2015, por medio del cual se expide el Decreto Único Reglamentario del Sector
Comercio, Industria y Turismo. Diario Oficial N° 49.523.
https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=76608
Mamurjonova, G., Karimov, K., Kenjayeva, A., Nazarova, O., Anarbaeva, M., &
Zikirov, B. (2025). Semantic Embedding Models for Technical Documentation
Analysis in Battery Management Systems Education. En 2025 Second
International Conference on Intelligent Technologies for Sustainable Electric
and Communications Systems (iTech SECOM). IEEE.
https://doi.org/10.1109/iTechSECOM64750.2025.11307623
Ong, M. I. U., Phon, D. N. E., & Ghani, N. A. (2025). An information system design
product theory for final-year project management system: A case study
evaluation. 2025 International Conference on Applied Artificial Intelligence,
Data Engineering and Sciences (ICAIDES), 1–5.
https://doi.org/10.1109/ICAIDES67265.2025.11404010
Ouyang, H. (2024). Design and implementation of practical project management
system for the cultivation of innovative ability of higher education students.
2024 6th International Academic Exchange Conference on Science and

Technology Innovation (IAECST).
https://doi.org/10.1109/IAECST64597.2024.11117867
Reshmi, R., Anilkumar, A., Sunder, A., Ananthakrishnan, T. H., & Prakash, A. C. P.
(2025). Enhancing academic project management with PAMS. Proceedings of
the ACCTHPA 2025 - Conference on Advanced Computing and
Communication Technologies for High Performance Applications.
https://doi.org/10.1109/ACCTHPA65749.2025.11168727
Retnowardhani, A., & Suroso, J. S. (2019). Project management information systems
(PMIS) for project management effectiveness: Comparison of case studies.
Proceedings of the 2019 International Conference on Computer Science,
Information Technology, and Electrical Engineering (ICOMITEE 2019),
160–164. https://doi.org/10.1109/ICOMITEE.2019.8921046
Robbes, R., & Lanza, M. (2007). Characterizing and understanding development
sessions. Proceedings of the 15th IEEE International Conference on Program
Comprehension (ICPC 2007), 155–166. https://doi.org/10.1109/ICPC.2007.12
Saputra, M., Pratami, D., & Chaeruddin, A. M. F. (2024). Expediting multiple projects
timelines with state-of-the-art dashboard management systems to improving
efficiency and minimizing delays. 8th International Conference on Information
Technology 2024, InCIT 2024, 209–214.
https://doi.org/10.1109/InCIT63192.2024.10810524
Silvestre, L., & Ureta, R. (2023). Discovering the development habits of software
development projects in academic scenarios. Proceedings of the 2023 42nd
IEEE International Conference of the Chilean Computer Science Society
(SCCC). https://doi.org/10.1109/SCCC59417.2023.10315756
Türkoğlu, Ö., & Aslankoç, O. G. (2025). AI powered integrated platform for
multi-stakeholder systems engineering and project management. 2025 IEEE
International Symposium on Systems Engineering (ISSE).
https://doi.org/10.1109/ISSE65546.2025.11370087
Universidad Autónoma de Bucaramanga. (2013, 27 de julio). Resolución N° 403 de
2013, por la cual se fija y adopta la política institucional de tratamiento de la
información y datos personales. https://www.unab.edu.co
Universidad Autónoma de Bucaramanga. (2021, 25 de mayo). Política de Propiedad
Intelectual de la Universidad Autónoma de Bucaramanga [Acta de Junta
Directiva N° 613]. https://www.unab.edu.co
Wang, J. (2024). A web-based innovation and entrepreneurship project management
system design for college students. 2024 International Conference on

Distributed Systems, Computer Networks and Cybersecurity (ICDSCNC).
https://doi.org/10.1109/ICDSCNC62492.2024.10939838
10. Anexos
Anexo A. Resolución N° 403 de 2013 — Política Institucional de Tratamiento de la
Información y Datos Personales de la UNAB
Universidad Autónoma de Bucaramanga. (2013, 27 de julio). Resolución N° 403 de 2013, por
la cual se fija y adopta la política institucional de tratamiento de la información y datos
personales. https://www.unab.edu.co
Anexo B. Política de Propiedad Intelectual de la Universidad Autónoma de
Bucaramanga
Universidad Autónoma de Bucaramanga. (2021, 25 de mayo). Política de Propiedad
Intelectual de la Universidad Autónoma de Bucaramanga [Acta de Junta Directiva N° 613].
https://www.unab.edu.co
Anexo C. Cronograma de actividades del proyecto
Arteaga Faria, J., & Afanador Quintero, M. (2026). Cronograma de actividades — Sistema
Centralizado PG UNAB [Archivo de hoja de cálculo].
https://docs.google.com/spreadsheets/d/1YtbpZLLLJuBV-LYOdpAlgj3XLgnXKwiq/edit?usp
=sharing