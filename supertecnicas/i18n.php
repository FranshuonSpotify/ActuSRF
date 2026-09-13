<?php
// supertecnicas/i18n.php
// Diccionario de idioma para el chrome de supertecnicas/index.php (login +
// plantilla del presidente). admin.php se queda en español, sin usar esto.
//
// $ST_TIPOS_I18N y $ST_AFINIDADES_I18N son copias en PHP de
// SF_TIPO_MAP/SF_AFINIDADES_MAP de _fuente/i18n.js (no se puede compartir
// el mismo fichero JS desde PHP sin añadir un paso de build) — si se
// retoca una traducción ahí, hay que retocarla aquí también.

require_once __DIR__ . '/lib.php'; // stEsc(), usada por stRenderSelectorIdioma()

const ST_IDIOMAS = ['es', 'en', 'pt', 'it', 'fr', 'ja', 'ko', 'pl', 'bg', 'sr'];

// código de idioma => código de país para la bandera (flagcdn.com), mismos
// pares que SF_LANGS en _fuente/i18n.js:8.
const ST_BANDERAS = [
    'es' => 'es', 'en' => 'gb', 'pt' => 'pt', 'it' => 'it', 'fr' => 'fr',
    'ja' => 'jp', 'ko' => 'kr', 'pl' => 'pl', 'bg' => 'bg', 'sr' => 'rs',
];

$ST_I18N = [
    'es' => [
        'login.titulo' => 'Supertécnicas',
        'login.subtitulo' => 'Entra con el correo y la contraseña con los que te registraste.',
        'login.campo_codigo' => 'Código de equipo',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entrar',
        'login.error' => 'Correo o contraseña incorrectos.',
        'roster.subtitulo' => 'Asigna hasta 4 supertécnicas por jugador. Los cambios se publican en la web al guardar.',
        'roster.ventana_abierta' => 'Ventana abierta',
        'roster.ventana_cerrada' => 'Ventana cerrada',
        'roster.cerrar_sesion' => 'Cerrar sesión',
        'roster.guardado_ok' => 'Cambios guardados correctamente.',
        'roster.ventana_cerrada_aviso' => 'La ventana de supertécnicas está cerrada. Puedes ver lo asignado, pero no editarlo.',
        'roster.sin_asignar' => 'Sin asignar',
        'roster.supertecnica' => 'Supertécnica',
        'campo.nombre' => 'Nombre',
        'campo.tipo' => 'Tipo',
        'campo.afinidad' => 'Afinidad',
        'campo.especial' => 'Especial',
        'campo.descripcion' => 'Descripción',
        'placeholder.nombre' => 'Sin usar',
        'placeholder.especial' => 'miximax, tótem…',
        'placeholder.descripcion' => 'Efecto de la supertécnica…',
        'roster.guardar' => 'Guardar cambios',
        'login.campo_email' => 'Correo',
        'login.campo_clave' => 'Contraseña',
        'registro.titulo' => 'Crear cuenta',
        'registro.subtitulo' => 'Regístrate tú mismo y elige tu equipo. No hace falta que el admin te mande nada.',
        'registro.campo_nombre' => 'Tu nombre',
        'registro.campo_clave2' => 'Repite la contraseña',
        'registro.campo_equipo' => 'Tu equipo',
        'registro.equipo_placeholder' => 'Elige tu equipo…',
        'registro.boton' => 'Crear cuenta',
        'registro.desde_login' => '¿No tienes cuenta? Regístrate',
        'registro.volver_login' => '¿Ya tienes cuenta? Entrar',
        'registro.aviso_email' => 'El correo no tiene que ser real, pero apúntatelo: es con lo que entras, y si se te olvida no hay forma de recuperarlo.',
        'registro.aviso_equipo' => 'Elige SOLO tu equipo. Cada registro queda guardado con su fecha y el admin de la liga lo revisa: entrar en el club de otro se ve.',
        'registro.error_nombre' => 'Escribe tu nombre.',
        'registro.error_email' => 'Eso no tiene formato de correo. Vale uno inventado, pero con la forma algo@algo.com.',
        'registro.error_clave_corta' => 'La contraseña tiene que tener al menos {minimo} caracteres.',
        'registro.error_claves_distintas' => 'Las dos contraseñas no coinciden.',
        'registro.error_equipo' => 'Ese equipo no existe o ya no está en la liga.',
        'registro.error_equipo_lleno' => 'Ese equipo ya tiene {maximo} presidentes. Si de verdad es el tuyo, habla con el admin de la liga.',
        'registro.error_email_duplicado' => 'Ya hay una cuenta con ese correo. Entra con ella, o usa otro.',
        'registro.error_escritura' => 'No se ha podido guardar. Vuelve a intentarlo; si sigue fallando, avisa al admin.',
        'registro.campo_codigo' => 'Código de invitación',
        'registro.aviso_codigo' => 'Solo si entras como copresidente: pídeselo al presidente que ya lleva el equipo. Si el equipo está libre, déjalo en blanco.',
        'registro.error_codigo' => 'Ese equipo ya tiene presidente. Para entrar como copresidente necesitas el código de invitación que te dé él.',
        'invitacion.titulo' => 'Invitar a un copresidente',
        'invitacion.explicacion' => 'Pásale este código a quien vaya a llevar {equipo} contigo. Solo vale una vez, y solo para tu equipo.',
        'invitacion.sin_codigo' => 'Todavía no has generado ningún código.',
        'invitacion.generar' => 'Generar código',
        'invitacion.regenerar' => 'Generar otro código',
        'invitacion.aviso_regenerar' => 'Si generas otro, el anterior deja de valer.',
        'invitacion.completo' => 'Tu equipo ya tiene sus {maximo} presidentes. No hacen falta más invitaciones.',
    ],
    'en' => [
        'login.titulo' => 'Supertechniques',
        'login.subtitulo' => 'Sign in with the email and password you signed up with.',
        'login.campo_codigo' => 'Team code',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Enter',
        'login.error' => 'Incorrect email or password.',
        'roster.subtitulo' => 'Assign up to 4 supertechniques per player. Changes go live on the site once saved.',
        'roster.ventana_abierta' => 'Window open',
        'roster.ventana_cerrada' => 'Window closed',
        'roster.cerrar_sesion' => 'Log out',
        'roster.guardado_ok' => 'Changes saved successfully.',
        'roster.ventana_cerrada_aviso' => "The supertechniques window is closed. You can view what's assigned, but not edit it.",
        'roster.sin_asignar' => 'Unassigned',
        'roster.supertecnica' => 'Supertechnique',
        'campo.nombre' => 'Name',
        'campo.tipo' => 'Type',
        'campo.afinidad' => 'Affinity',
        'campo.especial' => 'Special',
        'campo.descripcion' => 'Description',
        'placeholder.nombre' => 'Unused',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'What the supertechnique does…',
        'roster.guardar' => 'Save changes',
        'login.campo_email' => 'Email',
        'login.campo_clave' => 'Password',
        'registro.titulo' => 'Create account',
        'registro.subtitulo' => 'Sign yourself up and pick your team. The admin does not have to send you anything.',
        'registro.campo_nombre' => 'Your name',
        'registro.campo_clave2' => 'Repeat the password',
        'registro.campo_equipo' => 'Your team',
        'registro.equipo_placeholder' => 'Pick your team…',
        'registro.boton' => 'Create account',
        'registro.desde_login' => 'No account yet? Sign up',
        'registro.volver_login' => 'Already have an account? Sign in',
        'registro.aviso_email' => 'The email doesn\'t have to be real, but write it down: it\'s what you sign in with, and there\'s no way to recover it if you forget it.',
        'registro.aviso_equipo' => 'Pick ONLY your own team. Every sign-up is stored with its date and the league admin reviews them: taking someone else\'s club shows up.',
        'registro.error_nombre' => 'Enter your name.',
        'registro.error_email' => 'That isn\'t an email address. A made-up one is fine, but it has to look like something@something.com.',
        'registro.error_clave_corta' => 'The password must be at least {minimo} characters.',
        'registro.error_claves_distintas' => 'The two passwords don\'t match.',
        'registro.error_equipo' => 'That team doesn\'t exist, or it\'s no longer in the league.',
        'registro.error_equipo_lleno' => 'That team already has {maximo} presidents. If it really is yours, talk to the league admin.',
        'registro.error_email_duplicado' => 'There is already an account with that email. Sign in with it, or use another one.',
        'registro.error_escritura' => 'It couldn\'t be saved. Try again; if it keeps failing, tell the admin.',
        'registro.campo_codigo' => 'Invitation code',
        'registro.aviso_codigo' => 'Only if you are joining as a co-president: ask the president who already runs the team for it. If the team is free, leave it blank.',
        'registro.error_codigo' => 'That team already has a president. To join as co-president you need the invitation code they give you.',
        'invitacion.titulo' => 'Invite a co-president',
        'invitacion.explicacion' => 'Give this code to whoever is going to run {equipo} with you. It works once, and only for your team.',
        'invitacion.sin_codigo' => 'You haven\'t generated a code yet.',
        'invitacion.generar' => 'Generate code',
        'invitacion.regenerar' => 'Generate another code',
        'invitacion.aviso_regenerar' => 'If you generate another one, the previous code stops working.',
        'invitacion.completo' => 'Your team already has its {maximo} presidents. No more invitations needed.',
    ],
    'pt' => [
        'login.titulo' => 'Supertécnicas',
        'login.subtitulo' => 'Entra com o e-mail e a palavra-passe com que te registaste.',
        'login.campo_codigo' => 'Código da equipa',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entrar',
        'login.error' => 'E-mail ou palavra-passe incorretos.',
        'roster.subtitulo' => 'Atribui até 4 supertécnicas por jogador. As alterações são publicadas no site ao guardar.',
        'roster.ventana_abierta' => 'Janela aberta',
        'roster.ventana_cerrada' => 'Janela fechada',
        'roster.cerrar_sesion' => 'Terminar sessão',
        'roster.guardado_ok' => 'Alterações guardadas com sucesso.',
        'roster.ventana_cerrada_aviso' => 'A janela de supertécnicas está fechada. Podes ver o que está atribuído, mas não editar.',
        'roster.sin_asignar' => 'Sem atribuir',
        'roster.supertecnica' => 'Supertécnica',
        'campo.nombre' => 'Nome',
        'campo.tipo' => 'Tipo',
        'campo.afinidad' => 'Afinidade',
        'campo.especial' => 'Especial',
        'campo.descripcion' => 'Descrição',
        'placeholder.nombre' => 'Sem usar',
        'placeholder.especial' => 'miximax, tótem…',
        'placeholder.descripcion' => 'Efeito da supertécnica…',
        'roster.guardar' => 'Guardar alterações',
        'login.campo_email' => 'E-mail',
        'login.campo_clave' => 'Palavra-passe',
        'registro.titulo' => 'Criar conta',
        'registro.subtitulo' => 'Regista-te tu mesmo e escolhe a tua equipa. Não é preciso que o admin te envie nada.',
        'registro.campo_nombre' => 'O teu nome',
        'registro.campo_clave2' => 'Repete a palavra-passe',
        'registro.campo_equipo' => 'A tua equipa',
        'registro.equipo_placeholder' => 'Escolhe a tua equipa…',
        'registro.boton' => 'Criar conta',
        'registro.desde_login' => 'Ainda não tens conta? Regista-te',
        'registro.volver_login' => 'Já tens conta? Entrar',
        'registro.aviso_email' => 'O e-mail não tem de ser real, mas aponta-o: é com ele que entras e, se te esqueceres, não há forma de o recuperar.',
        'registro.aviso_equipo' => 'Escolhe SÓ a tua equipa. Cada registo fica guardado com a data e o admin da liga revê-os: entrar no clube de outro nota-se.',
        'registro.error_nombre' => 'Escreve o teu nome.',
        'registro.error_email' => 'Isso não tem formato de e-mail. Um inventado serve, mas com a forma algo@algo.com.',
        'registro.error_clave_corta' => 'A palavra-passe tem de ter pelo menos {minimo} caracteres.',
        'registro.error_claves_distintas' => 'As duas palavras-passe não coincidem.',
        'registro.error_equipo' => 'Essa equipa não existe ou já não está na liga.',
        'registro.error_equipo_lleno' => 'Essa equipa já tem {maximo} presidentes. Se for mesmo a tua, fala com o admin da liga.',
        'registro.error_email_duplicado' => 'Já existe uma conta com esse e-mail. Entra com ela ou usa outro.',
        'registro.error_escritura' => 'Não foi possível guardar. Tenta de novo; se continuar a falhar, avisa o admin.',
        'registro.campo_codigo' => 'Código de convite',
        'registro.aviso_codigo' => 'Só se entrares como copresidente: pede-o ao presidente que já leva a equipa. Se a equipa estiver livre, deixa em branco.',
        'registro.error_codigo' => 'Essa equipa já tem presidente. Para entrares como copresidente precisas do código de convite que ele te der.',
        'invitacion.titulo' => 'Convidar um copresidente',
        'invitacion.explicacion' => 'Dá este código a quem vai levar o {equipo} contigo. Só serve uma vez, e só para a tua equipa.',
        'invitacion.sin_codigo' => 'Ainda não geraste nenhum código.',
        'invitacion.generar' => 'Gerar código',
        'invitacion.regenerar' => 'Gerar outro código',
        'invitacion.aviso_regenerar' => 'Se gerares outro, o anterior deixa de servir.',
        'invitacion.completo' => 'A tua equipa já tem os seus {maximo} presidentes. Não são precisos mais convites.',
    ],
    'it' => [
        'login.titulo' => 'Supertecniche',
        'login.subtitulo' => 'Entra con l\'e-mail e la password con cui ti sei registrato.',
        'login.campo_codigo' => 'Codice squadra',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entra',
        'login.error' => 'E-mail o password errati.',
        'roster.subtitulo' => 'Assegna fino a 4 supertecniche per giocatore. Le modifiche vengono pubblicate sul sito al salvataggio.',
        'roster.ventana_abierta' => 'Finestra aperta',
        'roster.ventana_cerrada' => 'Finestra chiusa',
        'roster.cerrar_sesion' => 'Esci',
        'roster.guardado_ok' => 'Modifiche salvate correttamente.',
        'roster.ventana_cerrada_aviso' => 'La finestra delle supertecniche è chiusa. Puoi vedere quanto assegnato, ma non modificarlo.',
        'roster.sin_asignar' => 'Non assegnata',
        'roster.supertecnica' => 'Supertecnica',
        'campo.nombre' => 'Nome',
        'campo.tipo' => 'Tipo',
        'campo.afinidad' => 'Affinità',
        'campo.especial' => 'Speciale',
        'campo.descripcion' => 'Descrizione',
        'placeholder.nombre' => 'Non usata',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'Effetto della supertecnica…',
        'roster.guardar' => 'Salva modifiche',
        'login.campo_email' => 'E-mail',
        'login.campo_clave' => 'Password',
        'registro.titulo' => 'Crea account',
        'registro.subtitulo' => 'Registrati da solo e scegli la tua squadra. Non serve che l\'admin ti mandi niente.',
        'registro.campo_nombre' => 'Il tuo nome',
        'registro.campo_clave2' => 'Ripeti la password',
        'registro.campo_equipo' => 'La tua squadra',
        'registro.equipo_placeholder' => 'Scegli la tua squadra…',
        'registro.boton' => 'Crea account',
        'registro.desde_login' => 'Non hai un account? Registrati',
        'registro.volver_login' => 'Hai già un account? Entra',
        'registro.aviso_email' => 'L\'e-mail non deve essere vera, ma annotatela: è con quella che entri e, se la dimentichi, non c\'è modo di recuperarla.',
        'registro.aviso_equipo' => 'Scegli SOLO la tua squadra. Ogni registrazione viene salvata con la sua data e l\'admin della lega le controlla: prendere il club di un altro si vede.',
        'registro.error_nombre' => 'Scrivi il tuo nome.',
        'registro.error_email' => 'Quello non ha il formato di un\'e-mail. Una inventata va bene, ma con la forma qualcosa@qualcosa.com.',
        'registro.error_clave_corta' => 'La password deve avere almeno {minimo} caratteri.',
        'registro.error_claves_distintas' => 'Le due password non coincidono.',
        'registro.error_equipo' => 'Quella squadra non esiste o non è più nella lega.',
        'registro.error_equipo_lleno' => 'Quella squadra ha già {maximo} presidenti. Se è davvero la tua, parla con l\'admin della lega.',
        'registro.error_email_duplicado' => 'Esiste già un account con quell\'e-mail. Entra con quello, oppure usane un altro.',
        'registro.error_escritura' => 'Non è stato possibile salvare. Riprova; se continua a fallire, avvisa l\'admin.',
        'registro.campo_codigo' => 'Codice di invito',
        'registro.aviso_codigo' => 'Solo se entri come copresidente: chiedilo al presidente che già guida la squadra. Se la squadra è libera, lascialo vuoto.',
        'registro.error_codigo' => 'Quella squadra ha già un presidente. Per entrare come copresidente ti serve il codice di invito che ti dà lui.',
        'invitacion.titulo' => 'Invita un copresidente',
        'invitacion.explicacion' => 'Passa questo codice a chi guiderà {equipo} con te. Vale una volta sola, e solo per la tua squadra.',
        'invitacion.sin_codigo' => 'Non hai ancora generato nessun codice.',
        'invitacion.generar' => 'Genera codice',
        'invitacion.regenerar' => 'Genera un altro codice',
        'invitacion.aviso_regenerar' => 'Se ne generi un altro, il precedente smette di funzionare.',
        'invitacion.completo' => 'La tua squadra ha già i suoi {maximo} presidenti. Non servono altri inviti.',
    ],
    'fr' => [
        'login.titulo' => 'Supertechniques',
        'login.subtitulo' => 'Connecte-toi avec l\'e-mail et le mot de passe de ton inscription.',
        'login.campo_codigo' => "Code d'équipe",
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Entrer',
        'login.error' => 'E-mail ou mot de passe incorrects.',
        'roster.subtitulo' => "Attribue jusqu'à 4 supertechniques par joueur. Les changements sont publiés sur le site à l'enregistrement.",
        'roster.ventana_abierta' => 'Fenêtre ouverte',
        'roster.ventana_cerrada' => 'Fenêtre fermée',
        'roster.cerrar_sesion' => 'Se déconnecter',
        'roster.guardado_ok' => 'Modifications enregistrées avec succès.',
        'roster.ventana_cerrada_aviso' => 'La fenêtre des supertechniques est fermée. Tu peux voir ce qui est attribué, mais pas le modifier.',
        'roster.sin_asignar' => 'Non attribuée',
        'roster.supertecnica' => 'Supertechnique',
        'campo.nombre' => 'Nom',
        'campo.tipo' => 'Type',
        'campo.afinidad' => 'Affinité',
        'campo.especial' => 'Spécial',
        'campo.descripcion' => 'Description',
        'placeholder.nombre' => 'Non utilisé',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'Effet de la supertechnique…',
        'roster.guardar' => 'Enregistrer les modifications',
        'login.campo_email' => 'E-mail',
        'login.campo_clave' => 'Mot de passe',
        'registro.titulo' => 'Créer un compte',
        'registro.subtitulo' => 'Inscris-toi toi-même et choisis ton équipe. Pas besoin que l\'admin t\'envoie quoi que ce soit.',
        'registro.campo_nombre' => 'Ton nom',
        'registro.campo_clave2' => 'Répète le mot de passe',
        'registro.campo_equipo' => 'Ton équipe',
        'registro.equipo_placeholder' => 'Choisis ton équipe…',
        'registro.boton' => 'Créer un compte',
        'registro.desde_login' => 'Pas encore de compte ? Inscris-toi',
        'registro.volver_login' => 'Tu as déjà un compte ? Se connecter',
        'registro.aviso_email' => 'L\'e-mail n\'a pas besoin d\'être réel, mais note-le : c\'est avec lui que tu te connectes, et si tu l\'oublies il n\'y a aucun moyen de le récupérer.',
        'registro.aviso_equipo' => 'Choisis UNIQUEMENT ton équipe. Chaque inscription est enregistrée avec sa date et l\'admin de la ligue les vérifie : prendre le club d\'un autre se voit.',
        'registro.error_nombre' => 'Saisis ton nom.',
        'registro.error_email' => 'Ce n\'est pas un format d\'e-mail. Un e-mail inventé convient, mais il doit ressembler à quelquechose@quelquechose.com.',
        'registro.error_clave_corta' => 'Le mot de passe doit faire au moins {minimo} caractères.',
        'registro.error_claves_distintas' => 'Les deux mots de passe ne correspondent pas.',
        'registro.error_equipo' => 'Cette équipe n\'existe pas ou ne fait plus partie de la ligue.',
        'registro.error_equipo_lleno' => 'Cette équipe a déjà {maximo} présidents. Si c\'est vraiment la tienne, parle à l\'admin de la ligue.',
        'registro.error_email_duplicado' => 'Il existe déjà un compte avec cet e-mail. Connecte-toi avec, ou utilises-en un autre.',
        'registro.error_escritura' => 'L\'enregistrement a échoué. Réessaie ; si ça continue, préviens l\'admin.',
        'registro.campo_codigo' => 'Code d\'invitation',
        'registro.aviso_codigo' => 'Seulement si tu rejoins en tant que coprésident : demande-le au président qui dirige déjà l\'équipe. Si l\'équipe est libre, laisse vide.',
        'registro.error_codigo' => 'Cette équipe a déjà un président. Pour la rejoindre en tant que coprésident, il te faut le code d\'invitation qu\'il te donne.',
        'invitacion.titulo' => 'Inviter un coprésident',
        'invitacion.explicacion' => 'Donne ce code à la personne qui va diriger {equipo} avec toi. Il ne sert qu\'une fois, et seulement pour ton équipe.',
        'invitacion.sin_codigo' => 'Tu n\'as encore généré aucun code.',
        'invitacion.generar' => 'Générer un code',
        'invitacion.regenerar' => 'Générer un autre code',
        'invitacion.aviso_regenerar' => 'Si tu en génères un autre, le précédent cesse de fonctionner.',
        'invitacion.completo' => 'Ton équipe a déjà ses {maximo} présidents. Plus besoin d\'invitations.',
    ],
    'ja' => [
        'login.titulo' => 'スーパーテクニック',
        'login.subtitulo' => '登録したメールアドレスとパスワードでログインしてください。',
        'login.campo_codigo' => 'チームコード',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'ログイン',
        'login.error' => 'メールアドレスまたはパスワードが正しくありません。',
        'roster.subtitulo' => '選手ごとに最大4つのスーパーテクニックを設定できます。保存すると公式サイトに反映されます。',
        'roster.ventana_abierta' => '受付中',
        'roster.ventana_cerrada' => '受付終了',
        'roster.cerrar_sesion' => 'ログアウト',
        'roster.guardado_ok' => '変更を保存しました。',
        'roster.ventana_cerrada_aviso' => 'スーパーテクニックの受付は終了しています。内容の確認はできますが、編集はできません。',
        'roster.sin_asignar' => '未設定',
        'roster.supertecnica' => 'スーパーテクニック',
        'campo.nombre' => '名前',
        'campo.tipo' => 'タイプ',
        'campo.afinidad' => '属性',
        'campo.especial' => '特殊',
        'campo.descripcion' => '説明',
        'placeholder.nombre' => '未使用',
        'placeholder.especial' => 'ミキシマックス、トーテムなど…',
        'placeholder.descripcion' => 'スーパーテクニックの効果…',
        'roster.guardar' => '変更を保存',
        'login.campo_email' => 'メールアドレス',
        'login.campo_clave' => 'パスワード',
        'registro.titulo' => 'アカウントを作成',
        'registro.subtitulo' => '自分で登録して、自分のチームを選んでください。管理者から何かを受け取る必要はありません。',
        'registro.campo_nombre' => 'あなたの名前',
        'registro.campo_clave2' => 'パスワードをもう一度',
        'registro.campo_equipo' => 'あなたのチーム',
        'registro.equipo_placeholder' => 'チームを選んでください…',
        'registro.boton' => 'アカウントを作成',
        'registro.desde_login' => 'アカウントがありませんか？ 新規登録',
        'registro.volver_login' => 'すでにアカウントをお持ちですか？ ログイン',
        'registro.aviso_email' => 'メールアドレスは実在しなくても構いませんが、必ず控えておいてください。ログインに使うもので、忘れると復旧する方法はありません。',
        'registro.aviso_equipo' => '必ず自分のチームだけを選んでください。登録はすべて日時とともに記録され、リーグ管理者が確認します。他人のクラブを選ぶとすぐに分かります。',
        'registro.error_nombre' => '名前を入力してください。',
        'registro.error_email' => 'メールアドレスの形式になっていません。実在しないものでも構いませんが、なにか@なにか.com の形にしてください。',
        'registro.error_clave_corta' => 'パスワードは{minimo}文字以上にしてください。',
        'registro.error_claves_distintas' => '2つのパスワードが一致しません。',
        'registro.error_equipo' => 'そのチームは存在しないか、すでにリーグにいません。',
        'registro.error_equipo_lleno' => 'そのチームにはすでに{maximo}人の会長がいます。本当にあなたのチームなら、リーグ管理者に連絡してください。',
        'registro.error_email_duplicado' => 'そのメールアドレスのアカウントはすでにあります。そちらでログインするか、別のアドレスを使ってください。',
        'registro.error_escritura' => '保存できませんでした。もう一度お試しください。失敗が続く場合は管理者に連絡してください。',
        'registro.campo_codigo' => '招待コード',
        'registro.aviso_codigo' => '副会長として参加する場合のみ必要です。すでにチームを運営している会長に聞いてください。チームが空いている場合は空欄のままで構いません。',
        'registro.error_codigo' => 'そのチームにはすでに会長がいます。副会長として参加するには、会長から受け取る招待コードが必要です。',
        'invitacion.titulo' => '副会長を招待する',
        'invitacion.explicacion' => '{equipo} を一緒に運営する人にこのコードを渡してください。一度だけ、あなたのチームにのみ使えます。',
        'invitacion.sin_codigo' => 'まだコードを発行していません。',
        'invitacion.generar' => 'コードを発行',
        'invitacion.regenerar' => '別のコードを発行',
        'invitacion.aviso_regenerar' => '別のコードを発行すると、前のコードは使えなくなります。',
        'invitacion.completo' => 'あなたのチームにはすでに{maximo}人の会長がいます。これ以上の招待は必要ありません。',
    ],
    'ko' => [
        'login.titulo' => '슈퍼테크닉',
        'login.subtitulo' => '가입할 때 사용한 이메일과 비밀번호로 로그인하세요.',
        'login.campo_codigo' => '팀 코드',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => '입장',
        'login.error' => '이메일 또는 비밀번호가 올바르지 않습니다.',
        'roster.subtitulo' => '선수당 최대 4개의 슈퍼테크닉을 지정할 수 있습니다. 저장하면 웹사이트에 바로 반영됩니다.',
        'roster.ventana_abierta' => '접수 중',
        'roster.ventana_cerrada' => '접수 마감',
        'roster.cerrar_sesion' => '로그아웃',
        'roster.guardado_ok' => '변경 사항이 저장되었습니다.',
        'roster.ventana_cerrada_aviso' => '슈퍼테크닉 접수 기간이 아닙니다. 지정된 내용은 볼 수 있지만 수정할 수는 없습니다.',
        'roster.sin_asignar' => '미지정',
        'roster.supertecnica' => '슈퍼테크닉',
        'campo.nombre' => '이름',
        'campo.tipo' => '유형',
        'campo.afinidad' => '속성',
        'campo.especial' => '특수',
        'campo.descripcion' => '설명',
        'placeholder.nombre' => '미사용',
        'placeholder.especial' => '믹시맥스, 토템 등…',
        'placeholder.descripcion' => '슈퍼테크닉 효과…',
        'roster.guardar' => '변경 사항 저장',
        'login.campo_email' => '이메일',
        'login.campo_clave' => '비밀번호',
        'registro.titulo' => '계정 만들기',
        'registro.subtitulo' => '직접 가입하고 자신의 팀을 선택하세요. 관리자가 무언가를 보내줄 필요가 없습니다.',
        'registro.campo_nombre' => '이름',
        'registro.campo_clave2' => '비밀번호 확인',
        'registro.campo_equipo' => '내 팀',
        'registro.equipo_placeholder' => '팀을 선택하세요…',
        'registro.boton' => '계정 만들기',
        'registro.desde_login' => '계정이 없으신가요? 가입하기',
        'registro.volver_login' => '이미 계정이 있으신가요? 로그인',
        'registro.aviso_email' => '이메일은 실제 주소가 아니어도 되지만 꼭 적어 두세요. 로그인에 사용하며, 잊어버리면 복구할 방법이 없습니다.',
        'registro.aviso_equipo' => '반드시 자신의 팀만 선택하세요. 모든 가입은 날짜와 함께 기록되며 리그 관리자가 확인합니다. 남의 클럽을 선택하면 바로 드러납니다.',
        'registro.error_nombre' => '이름을 입력하세요.',
        'registro.error_email' => '이메일 형식이 아닙니다. 실제 주소가 아니어도 되지만 무언가@무언가.com 형태여야 합니다.',
        'registro.error_clave_corta' => '비밀번호는 최소 {minimo}자 이상이어야 합니다.',
        'registro.error_claves_distintas' => '두 비밀번호가 일치하지 않습니다.',
        'registro.error_equipo' => '그 팀은 존재하지 않거나 더 이상 리그에 없습니다.',
        'registro.error_equipo_lleno' => '그 팀에는 이미 회장이 {maximo}명 있습니다. 정말 본인 팀이라면 리그 관리자에게 문의하세요.',
        'registro.error_email_duplicado' => '이미 그 이메일로 만든 계정이 있습니다. 그 계정으로 로그인하거나 다른 이메일을 사용하세요.',
        'registro.error_escritura' => '저장하지 못했습니다. 다시 시도하세요. 계속 실패하면 관리자에게 알리세요.',
        'registro.campo_codigo' => '초대 코드',
        'registro.aviso_codigo' => '공동 구단주로 참여하는 경우에만 필요합니다. 이미 팀을 맡고 있는 구단주에게 요청하세요. 팀이 비어 있다면 비워 두세요.',
        'registro.error_codigo' => '그 팀에는 이미 구단주가 있습니다. 공동 구단주로 참여하려면 구단주가 주는 초대 코드가 필요합니다.',
        'invitacion.titulo' => '공동 구단주 초대',
        'invitacion.explicacion' => '{equipo}을(를) 함께 운영할 사람에게 이 코드를 전달하세요. 한 번만, 그리고 본인 팀에만 사용할 수 있습니다.',
        'invitacion.sin_codigo' => '아직 코드를 만들지 않았습니다.',
        'invitacion.generar' => '코드 생성',
        'invitacion.regenerar' => '다른 코드 생성',
        'invitacion.aviso_regenerar' => '다른 코드를 만들면 이전 코드는 사용할 수 없게 됩니다.',
        'invitacion.completo' => '팀에 이미 구단주가 {maximo}명 있습니다. 더 이상 초대가 필요하지 않습니다.',
    ],
    'pl' => [
        'login.titulo' => 'Supertechniki',
        'login.subtitulo' => 'Zaloguj się e-mailem i hasłem podanymi przy rejestracji.',
        'login.campo_codigo' => 'Kod drużyny',
        'login.campo_pin' => 'PIN',
        'login.boton_entrar' => 'Wejdź',
        'login.error' => 'Nieprawidłowy e-mail lub hasło.',
        'roster.subtitulo' => 'Przypisz do 4 supertechnik na zawodnika. Zmiany trafiają na stronę po zapisaniu.',
        'roster.ventana_abierta' => 'Okno otwarte',
        'roster.ventana_cerrada' => 'Okno zamknięte',
        'roster.cerrar_sesion' => 'Wyloguj się',
        'roster.guardado_ok' => 'Zmiany zapisane pomyślnie.',
        'roster.ventana_cerrada_aviso' => 'Okno supertechnik jest zamknięte. Możesz zobaczyć przypisane techniki, ale nie edytować ich.',
        'roster.sin_asignar' => 'Nieprzypisana',
        'roster.supertecnica' => 'Supertechnika',
        'campo.nombre' => 'Nazwa',
        'campo.tipo' => 'Typ',
        'campo.afinidad' => 'Żywioł',
        'campo.especial' => 'Specjalna',
        'campo.descripcion' => 'Opis',
        'placeholder.nombre' => 'Nieużywana',
        'placeholder.especial' => 'miximax, totem…',
        'placeholder.descripcion' => 'Efekt supertechniki…',
        'roster.guardar' => 'Zapisz zmiany',
        'login.campo_email' => 'E-mail',
        'login.campo_clave' => 'Hasło',
        'registro.titulo' => 'Załóż konto',
        'registro.subtitulo' => 'Zarejestruj się sam i wybierz swoją drużynę. Admin nie musi ci niczego wysyłać.',
        'registro.campo_nombre' => 'Twoje imię',
        'registro.campo_clave2' => 'Powtórz hasło',
        'registro.campo_equipo' => 'Twoja drużyna',
        'registro.equipo_placeholder' => 'Wybierz swoją drużynę…',
        'registro.boton' => 'Załóż konto',
        'registro.desde_login' => 'Nie masz konta? Zarejestruj się',
        'registro.volver_login' => 'Masz już konto? Zaloguj się',
        'registro.aviso_email' => 'E-mail nie musi być prawdziwy, ale go zapisz: to nim się logujesz, a jeśli go zapomnisz, nie da się go odzyskać.',
        'registro.aviso_equipo' => 'Wybierz TYLKO swoją drużynę. Każda rejestracja jest zapisywana z datą, a admin ligi je sprawdza: wejście do cudzego klubu widać.',
        'registro.error_nombre' => 'Wpisz swoje imię.',
        'registro.error_email' => 'To nie ma formatu adresu e-mail. Wymyślony jest w porządku, ale musi wyglądać jak cos@cos.com.',
        'registro.error_clave_corta' => 'Hasło musi mieć co najmniej {minimo} znaków.',
        'registro.error_claves_distintas' => 'Oba hasła nie są takie same.',
        'registro.error_equipo' => 'Ta drużyna nie istnieje albo już nie gra w lidze.',
        'registro.error_equipo_lleno' => 'Ta drużyna ma już {maximo} prezesów. Jeśli naprawdę jest twoja, napisz do admina ligi.',
        'registro.error_email_duplicado' => 'Konto z tym adresem już istnieje. Zaloguj się na nie albo użyj innego.',
        'registro.error_escritura' => 'Nie udało się zapisać. Spróbuj ponownie; jeśli nadal nie działa, daj znać adminowi.',
        'registro.campo_codigo' => 'Kod zaproszenia',
        'registro.aviso_codigo' => 'Tylko jeśli dołączasz jako współprezes: poproś o niego prezesa, który już prowadzi drużynę. Jeśli drużyna jest wolna, zostaw puste.',
        'registro.error_codigo' => 'Ta drużyna ma już prezesa. Aby dołączyć jako współprezes, potrzebujesz kodu zaproszenia, który on ci poda.',
        'invitacion.titulo' => 'Zaproś współprezesa',
        'invitacion.explicacion' => 'Przekaż ten kod osobie, która poprowadzi {equipo} razem z tobą. Działa raz i tylko dla twojej drużyny.',
        'invitacion.sin_codigo' => 'Nie wygenerowałeś jeszcze żadnego kodu.',
        'invitacion.generar' => 'Wygeneruj kod',
        'invitacion.regenerar' => 'Wygeneruj inny kod',
        'invitacion.aviso_regenerar' => 'Jeśli wygenerujesz inny, poprzedni przestanie działać.',
        'invitacion.completo' => 'Twoja drużyna ma już {maximo} prezesów. Więcej zaproszeń nie trzeba.',
    ],
    'bg' => [
        'login.titulo' => 'Суперумения',
        'login.subtitulo' => 'Влез с имейла и паролата, с които се регистрира.',
        'login.campo_codigo' => 'Код на отбора',
        'login.campo_pin' => 'ПИН',
        'login.boton_entrar' => 'Вход',
        'login.error' => 'Грешен имейл или парола.',
        'roster.subtitulo' => 'Задай до 4 суперумения на играч. Промените се публикуват на сайта при запазване.',
        'roster.ventana_abierta' => 'Прозорецът е отворен',
        'roster.ventana_cerrada' => 'Прозорецът е затворен',
        'roster.cerrar_sesion' => 'Изход',
        'roster.guardado_ok' => 'Промените са запазени успешно.',
        'roster.ventana_cerrada_aviso' => 'Прозорецът за суперумения е затворен. Можеш да видиш зададеното, но не и да го редактираш.',
        'roster.sin_asignar' => 'Незададено',
        'roster.supertecnica' => 'Суперумение',
        'campo.nombre' => 'Име',
        'campo.tipo' => 'Вид',
        'campo.afinidad' => 'Стихия',
        'campo.especial' => 'Специално',
        'campo.descripcion' => 'Описание',
        'placeholder.nombre' => 'Неизползвано',
        'placeholder.especial' => 'миксимакс, тотем…',
        'placeholder.descripcion' => 'Ефект на суперумението…',
        'roster.guardar' => 'Запази промените',
        'login.campo_email' => 'Имейл',
        'login.campo_clave' => 'Парола',
        'registro.titulo' => 'Създай акаунт',
        'registro.subtitulo' => 'Регистрирай се сам и избери своя отбор. Не е нужно админът да ти изпраща нищо.',
        'registro.campo_nombre' => 'Твоето име',
        'registro.campo_clave2' => 'Повтори паролата',
        'registro.campo_equipo' => 'Твоят отбор',
        'registro.equipo_placeholder' => 'Избери своя отбор…',
        'registro.boton' => 'Създай акаунт',
        'registro.desde_login' => 'Нямаш акаунт? Регистрирай се',
        'registro.volver_login' => 'Вече имаш акаунт? Вход',
        'registro.aviso_email' => 'Имейлът не е нужно да е истински, но си го запиши: с него влизаш и ако го забравиш, няма начин да бъде възстановен.',
        'registro.aviso_equipo' => 'Избери САМО своя отбор. Всяка регистрация се запазва с датата си и админът на лигата ги преглежда: влизането в чужд клуб си личи.',
        'registro.error_nombre' => 'Въведи името си.',
        'registro.error_email' => 'Това не е във формат на имейл. Измисленият върши работа, но трябва да изглежда като нещо@нещо.com.',
        'registro.error_clave_corta' => 'Паролата трябва да е поне {minimo} знака.',
        'registro.error_claves_distintas' => 'Двете пароли не съвпадат.',
        'registro.error_equipo' => 'Този отбор не съществува или вече не е в лигата.',
        'registro.error_equipo_lleno' => 'Този отбор вече има {maximo} президенти. Ако наистина е твоят, пиши на админа на лигата.',
        'registro.error_email_duplicado' => 'Вече има акаунт с този имейл. Влез с него или използвай друг.',
        'registro.error_escritura' => 'Не успяхме да запазим. Опитай отново; ако продължава, съобщи на администратора.',
        'registro.campo_codigo' => 'Код за покана',
        'registro.aviso_codigo' => 'Само ако влизаш като съпрезидент: поискай го от президента, който вече води отбора. Ако отборът е свободен, остави празно.',
        'registro.error_codigo' => 'Този отбор вече има президент. За да влезеш като съпрезидент, ти трябва кодът за покана, който той ти даде.',
        'invitacion.titulo' => 'Покани съпрезидент',
        'invitacion.explicacion' => 'Дай този код на човека, който ще води {equipo} с теб. Важи само веднъж и само за твоя отбор.',
        'invitacion.sin_codigo' => 'Още не си генерирал код.',
        'invitacion.generar' => 'Генерирай код',
        'invitacion.regenerar' => 'Генерирай друг код',
        'invitacion.aviso_regenerar' => 'Ако генерираш друг, предишният спира да важи.',
        'invitacion.completo' => 'Отборът ти вече има своите {maximo} президенти. Не са нужни повече покани.',
    ],
    'sr' => [
        'login.titulo' => 'Супертехнике',
        'login.subtitulo' => 'Пријави се мејлом и лозинком којима си се регистровао.',
        'login.campo_codigo' => 'Код тима',
        'login.campo_pin' => 'ПИН',
        'login.boton_entrar' => 'Улаз',
        'login.error' => 'Погрешан мејл или лозинка.',
        'roster.subtitulo' => 'Додели до 4 супертехнике по играчу. Измене се објављују на сајту чим се сачувају.',
        'roster.ventana_abierta' => 'Прозор отворен',
        'roster.ventana_cerrada' => 'Прозор затворен',
        'roster.cerrar_sesion' => 'Одјава',
        'roster.guardado_ok' => 'Измене су успешно сачуване.',
        'roster.ventana_cerrada_aviso' => 'Прозор за супертехнике је затворен. Можеш видети додељено, али не и мењати.',
        'roster.sin_asignar' => 'Није додељено',
        'roster.supertecnica' => 'Супертехника',
        'campo.nombre' => 'Име',
        'campo.tipo' => 'Тип',
        'campo.afinidad' => 'Афинитет',
        'campo.especial' => 'Специјално',
        'campo.descripcion' => 'Опис',
        'placeholder.nombre' => 'Неискоришћено',
        'placeholder.especial' => 'миксимакс, тотем…',
        'placeholder.descripcion' => 'Ефекат супертехнике…',
        'roster.guardar' => 'Сачувај измене',
        'login.campo_email' => 'Мејл',
        'login.campo_clave' => 'Лозинка',
        'registro.titulo' => 'Направи налог',
        'registro.subtitulo' => 'Региструј се сам и изабери свој тим. Није потребно да ти админ шаље било шта.',
        'registro.campo_nombre' => 'Твоје име',
        'registro.campo_clave2' => 'Понови лозинку',
        'registro.campo_equipo' => 'Твој тим',
        'registro.equipo_placeholder' => 'Изабери свој тим…',
        'registro.boton' => 'Направи налог',
        'registro.desde_login' => 'Немаш налог? Региструј се',
        'registro.volver_login' => 'Већ имаш налог? Пријава',
        'registro.aviso_email' => 'Мејл не мора да буде прави, али га запиши: њиме се пријављујеш и ако га заборавиш, нема начина да се врати.',
        'registro.aviso_equipo' => 'Изабери САМО свој тим. Свака регистрација се чува са датумом и админ лиге их прегледа: улазак у туђи клуб се види.',
        'registro.error_nombre' => 'Упиши своје име.',
        'registro.error_email' => 'То није у формату мејла. Измишљени је у реду, али мора да изгледа као нешто@нешто.com.',
        'registro.error_clave_corta' => 'Лозинка мора имати најмање {minimo} карактера.',
        'registro.error_claves_distintas' => 'Две лозинке се не подударају.',
        'registro.error_equipo' => 'Тај тим не постоји или више није у лиги.',
        'registro.error_equipo_lleno' => 'Тај тим већ има {maximo} председника. Ако је заиста твој, јави се админу лиге.',
        'registro.error_email_duplicado' => 'Већ постоји налог са тим мејлом. Пријави се њиме или користи други.',
        'registro.error_escritura' => 'Чување није успело. Покушај поново; ако и даље не ради, јави админу.',
        'registro.campo_codigo' => 'Код позивнице',
        'registro.aviso_codigo' => 'Само ако улазиш као копредседник: затражи га од председника који већ води тим. Ако је тим слободан, остави празно.',
        'registro.error_codigo' => 'Тај тим већ има председника. Да уђеш као копредседник, треба ти код позивнице који ти он да.',
        'invitacion.titulo' => 'Позови копредседника',
        'invitacion.explicacion' => 'Дај овај код особи која ће водити {equipo} са тобом. Важи само једном и само за твој тим.',
        'invitacion.sin_codigo' => 'Још ниси направио ниједан код.',
        'invitacion.generar' => 'Направи код',
        'invitacion.regenerar' => 'Направи други код',
        'invitacion.aviso_regenerar' => 'Ако направиш други, претходни престаје да важи.',
        'invitacion.completo' => 'Твој тим већ има својих {maximo} председника. Више позивница није потребно.',
    ],
];

// Copia en PHP de SF_TIPO_MAP (_fuente/i18n.js) — mismos 4 valores
// canónicos que ST_TIPOS de lib.php ('' se maneja aparte, no tiene
// traducción: la plantilla imprime '—' directamente).
$ST_TIPOS_I18N = [
    'tiro' => ['es'=>'Tiro','en'=>'Shot','pt'=>'Chute','it'=>'Tiro','fr'=>'Tir','ja'=>'シュート','ko'=>'슛','pl'=>'Strzał','bg'=>'Удар','sr'=>'Шут'],
    'regate' => ['es'=>'Regate','en'=>'Dribble','pt'=>'Drible','it'=>'Dribbling','fr'=>'Dribble','ja'=>'ドリブル','ko'=>'드리블','pl'=>'Drybling','bg'=>'Дрибъл','sr'=>'Дриблинг'],
    'bloqueo' => ['es'=>'Bloqueo','en'=>'Block','pt'=>'Bloqueio','it'=>'Blocco','fr'=>'Blocage','ja'=>'ブロック','ko'=>'블록','pl'=>'Blok','bg'=>'Блок','sr'=>'Блок'],
    'parada' => ['es'=>'Parada','en'=>'Save','pt'=>'Defesa','it'=>'Parata','fr'=>'Arrêt','ja'=>'セーブ','ko'=>'세이브','pl'=>'Obrona','bg'=>'Спасяване','sr'=>'Одбрана'],
];

// Copia en PHP de SF_AFINIDADES_MAP (_fuente/i18n.js:247-251) — mismos 5
// valores canónicos que ST_AFINIDADES de lib.php.
$ST_AFINIDADES_I18N = [
    'fuego' => ['es'=>'Fuego','en'=>'Fire','pt'=>'Fogo','it'=>'Fuoco','fr'=>'Feu','ja'=>'炎','ko'=>'화염','pl'=>'Ogień','bg'=>'Огън','sr'=>'Ватра'],
    'aire' => ['es'=>'Aire','en'=>'Wind','pt'=>'Ar','it'=>'Aria','fr'=>'Air','ja'=>'風','ko'=>'바람','pl'=>'Wiatr','bg'=>'Въздух','sr'=>'Ветар'],
    'bosque' => ['es'=>'Bosque','en'=>'Forest','pt'=>'Floresta','it'=>'Foresta','fr'=>'Forêt','ja'=>'森','ko'=>'숲','pl'=>'Las','bg'=>'Гора','sr'=>'Шума'],
    'montaña' => ['es'=>'Montaña','en'=>'Mountain','pt'=>'Montanha','it'=>'Montagna','fr'=>'Montagne','ja'=>'山','ko'=>'산','pl'=>'Góra','bg'=>'Планина','sr'=>'Планина'],
    'neutro' => ['es'=>'Neutro','en'=>'Void','pt'=>'Vazio','it'=>'Vuoto','fr'=>'Vide','ja'=>'無','ko'=>'무','pl'=>'Pustka','bg'=>'Празнота','sr'=>'Празнина'],
];

$GLOBALS['ST_IDIOMA_ACTUAL'] = 'es';

function stEstablecerIdioma(string $idioma): void {
    $GLOBALS['ST_IDIOMA_ACTUAL'] = in_array($idioma, ST_IDIOMAS, true) ? $idioma : 'es';
}

// Parsea una cabecera Accept-Language ("es-ES,es;q=0.9,en;q=0.8") y
// devuelve el primer subtag principal soportado, ordenado por calidad (q)
// descendente. Formato de cabecera HTTP estándar, sin librerías.
function stDetectarIdiomaNavegador(string $cabecera): string {
    if (trim($cabecera) === '') return 'es';

    $candidatos = [];
    foreach (explode(',', $cabecera) as $parte) {
        $parte = trim($parte);
        if ($parte === '') continue;
        $q = 1.0;
        if (preg_match('/;\s*q=([0-9.]+)/', $parte, $m)) {
            $q = (float) $m[1];
        }
        $subtag = strtolower(substr(preg_replace('/;.*/', '', $parte), 0, 2));
        $candidatos[] = ['subtag' => $subtag, 'q' => $q];
    }

    usort($candidatos, function ($a, $b) { return $b['q'] <=> $a['q']; });

    foreach ($candidatos as $c) {
        if (in_array($c['subtag'], ST_IDIOMAS, true)) return $c['subtag'];
    }
    return 'es';
}

// Orden de resolución: ?lang= en la URL (y se guarda en sesión) -> idioma
// ya guardado en sesión -> cabecera Accept-Language -> español.
function stResolverIdioma(): string {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ST_IDIOMAS, true)) {
        $_SESSION['st_lang'] = $_GET['lang'];
        return $_GET['lang'];
    }

    if (isset($_SESSION['st_lang']) && in_array($_SESSION['st_lang'], ST_IDIOMAS, true)) {
        return $_SESSION['st_lang'];
    }

    $detectado = stDetectarIdiomaNavegador($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $_SESSION['st_lang'] = $detectado;
    return $detectado;
}

// $marcadores sustituye {nombre} por su valor DESPUÉS de traducir. Al revés se
// traduciría una cadena que ya lleva cifras dentro y el diccionario dejaría de
// casar. Sin marcadores se comporta exactamente igual que antes.
function stT(string $clave, array $marcadores = []): string {
    global $ST_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    if (isset($ST_I18N[$idioma][$clave])) {
        $texto = $ST_I18N[$idioma][$clave];
    } elseif (isset($ST_I18N['es'][$clave])) {
        $texto = $ST_I18N['es'][$clave];
    } else {
        return '[' . $clave . ']';
    }
    foreach ($marcadores as $nombre => $valor) {
        $texto = str_replace('{' . $nombre . '}', (string) $valor, $texto);
    }
    return $texto;
}

function stTipoLabel(string $tipo): string {
    global $ST_TIPOS_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    $original = trim($tipo);
    if ($original === '') return '';
    $clave = mb_strtolower($original, 'UTF-8');
    if (!isset($ST_TIPOS_I18N[$clave])) return $original;
    return $ST_TIPOS_I18N[$clave][$idioma] ?? $ST_TIPOS_I18N[$clave]['es'];
}

function stAfinidadLabel(string $afinidad): string {
    global $ST_AFINIDADES_I18N;
    $idioma = $GLOBALS['ST_IDIOMA_ACTUAL'];
    $original = trim($afinidad);
    if ($original === '') return '';
    $clave = mb_strtolower($original, 'UTF-8');
    if (!isset($ST_AFINIDADES_I18N[$clave])) return $original;
    return $ST_AFINIDADES_I18N[$clave][$idioma] ?? $ST_AFINIDADES_I18N[$clave]['es'];
}

// Fila de banderas: un enlace <a href="?lang=xx"> por idioma, sin JS.
// stEsc() viene de supertecnicas/lib.php (ya cargado por index.php antes
// de incluir este fichero).
function stRenderSelectorIdioma(): void {
    echo '<nav class="st-idiomas" aria-label="Idioma">';
    foreach (ST_BANDERAS as $codigo => $pais) {
        $activo = $GLOBALS['ST_IDIOMA_ACTUAL'] === $codigo;
        echo '<a href="?lang=' . stEsc($codigo) . '"'
            . ($activo ? ' class="activo" aria-current="true"' : '')
            . '><img src="https://flagcdn.com/16x12/' . stEsc($pais) . '.png" alt="' . stEsc($codigo) . '" width="16" height="12" loading="lazy"></a>';
    }
    echo '</nav>';
}
