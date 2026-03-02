<?php
/**
 * Estructura oficial de departamentos y municipios de Bolivia
 * Datos organizados jerárquicamente para fácil acceso
 */

function obtenerDepartamentosBolivia() {
    return [
        'LPZ' => 'La Paz',
        'SCZ' => 'Santa Cruz',
        'CBB' => 'Cochabamba',
        'CHQ' => 'Chuquisaca',
        'TJA' => 'Tarija',
        'ORU' => 'Oruro',
        'PTS' => 'Potosí',
        'BEN' => 'Beni',
        'PND' => 'Pando'
    ];
}

function obtenerMunicipiosPorDepartamento() {
    return [
        'LPZ' => [ // La Paz - Ciudades grandes primero
            'La Paz', 'El Alto', 'Viacha', 'Achocalla', 'Caranavi', 'Copacabana',
            'Coroico', 'Desaguadero', 'Patacamaya', 'Sorata', 'Achacachi', 'Ancoraimes',
            'Apolo', 'Aucapata', 'Ayata', 'Batallas', 'Caquiaviri', 'Carabuco',
            'Catacora', 'Chacarilla', 'Charaña', 'Chulumani', 'Colquencha', 'Colquiri',
            'Combaya', 'Coripata', 'Curva', 'Escoma', 'Guanay', 'Guaqui',
            'Humanata', 'Ichoca', 'Inquisivi', 'Irupana', 'Ixiamas', 'Laja',
            'Licoma', 'Luribay', 'Mapiri', 'Mecapaca', 'Mocomoco', 'Nazacara de Pacajes',
            'Palca', 'Palos Blancos', 'Papel Pampa', 'Pelechuco', 'Puerto Acosta', 'Puerto Carabuco',
            'Pucarani', 'Quiabaya', 'Quime', 'San Buenaventura', 'San Pedro de Curahuara', 'Santiago de Callapa',
            'Santiago de Machaca', 'Sapahaqui', 'Tacacoma', 'Taraco', 'Teoponte', 'Tiahuanacu',
            'Tipuani', 'Tito Yupanqui', 'Umala', 'Waldo Ballivián', 'Yanacachi'
        ],
        'SCZ' => [ // Santa Cruz - Ciudades grandes primero
            'Santa Cruz de la Sierra', 'Montero', 'La Guardia', 'Warnes', 'Cotoca', 'El Torno',
            'Camiri', 'Puerto Suárez', 'San Ignacio de Velasco', 'Portachuelo', 'Yapacaní', 'Buena Vista',
            'Pailón', 'San Julián', 'Concepción', 'Vallegrande', 'Ascensión de Guarayos', 'Boyuibe',
            'Cabezas', 'Charagua', 'Colpa Bélgica', 'Comarapa', 'Cuatro Cañadas', 'Cuevo',
            'El Puente', 'Fernández Alonso', 'General Saavedra', 'Gutiérrez', 'Hardeman', 'Lagunillas',
            'Mairana', 'Mineros', 'Moro Moro', 'Okinawa Uno', 'Pampa Grande', 'Postrer Valle',
            'Puerto Quijarro', 'Quirusillas', 'Robore', 'Saipina', 'Samaipata', 'San Antonio de Lomerío',
            'San Carlos', 'San José de Chiquitos', 'San Juan del Surutú', 'San Matías', 'San Miguel de Velasco',
            'San Pedro', 'San Rafael', 'San Ramón', 'Santa Rosa del Sara', 'Trigal',
            'Urubichá'
        ],
        'CBB' => [ // Cochabamba - Ciudades grandes primero
            'Cochabamba', 'Sacaba', 'Quillacollo', 'Colcapirhua', 'Tiquipaya', 'Vinto',
            'Sipe Sipe', 'Punata', 'Cliza', 'Villa Tunari', 'Aiquile', 'Arani',
            'Arbieto', 'Arque', 'Bolívar', 'Capinota', 'Cercado', 'Chimoré',
            'Colomi', 'Cuchumuela', 'Entre Ríos', 'Independencia', 'Irpa Irpa', 'Mizque',
            'Morochata', 'Omereque', 'Pasorapa', 'Pojo', 'Sacabamba', 'San Benito',
            'Santivañez', 'Shinahota', 'Tacopaya', 'Tapacarí', 'Tarata', 'Tiraque',
            'Toco', 'Tolata', 'Totora', 'Vacas', 'Vila Vila', 'Villa Rivero'
        ],
        'CHQ' => [ // Chuquisaca - Ciudades grandes primero
            'Sucre', 'Monteagudo', 'Camargo', 'Tarabuco', 'Azurduy', 'Culpina',
            'El Villar', 'Huacaya', 'Huacareta', 'Icla', 'Incahuasi', 'Las Carreras',
            'Macharetí', 'Mojocoya', 'Padilla', 'Poroma', 'Presto', 'San Lucas',
            'San Pablo de Huacareta', 'Sopachuy', 'Tarvita', 'Tomina', 'Villa Abecia', 'Villa Alcalá',
            'Villa Azurduy', 'Villa Mojocoya', 'Villa Serrano', 'Villa Vaca Guzmán', 'Yamparáez', 'Zudáñez'
        ],
        'TJA' => [ // Tarija - Ciudades grandes primero
            'Tarija', 'Yacuiba', 'Villamontes', 'Bermejo', 'Caraparí', 'El Puente',
            'Entre Ríos', 'Padcaya', 'San Lorenzo', 'Uriondo', 'Yunchará'
        ],
        'ORU' => [ // Oruro - Ciudades grandes primero
            'Oruro', 'Huanuni', 'Challapata', 'Caracollo', 'Antequera', 'Belén de Andamarca',
            'Chipaya', 'Choque Cota', 'Corque', 'Cruz de Machacamarca', 'Curahuara de Carangas', 'El Choro',
            'Escara', 'Esmeralda', 'Eucaliptus', 'Huachacalla', 'Huayllamarca', 'La Rivera',
            'Machacamarca', 'Nor Carangas', 'Pampa Aullagas', 'Poopó', 'Sabaya', 'Salinas de Garci Mendoza',
            'Santiago de Andamarca', 'Santiago de Huari', 'Santuario de Quillacas', 'Todos Santos', 'Toledo', 'Totora',
            'Turco', 'Yunguyo de Litoral'
        ],
        'PTS' => [ // Potosí - Ciudades grandes primero
            'Potosí', 'Llallagua', 'Uyuni', 'Tupiza', 'Villazón', 'Betanzos',
            'Uncía', 'Atocha', 'Caiza D', 'Caripuyo', 'Chaqui', 'Chayanta',
            'Colcha K', 'Colquechaca', 'Cotagaita', 'Llica', 'Mojinete', 'Ocurí',
            'Pocoata', 'Porco', 'Puna', 'Ravelo', 'Sacaca', 'San Agustín',
            'San Antonio de Esmoruco', 'San Pablo de Lípez', 'San Pedro de Buena Vista', 'San Pedro de Quemes', 'Tacobamba', 'Tahua',
            'Tinguipaya', 'Tomave', 'Toro Toro', 'Villa de Yocalla', 'Vitichi'
        ],
        'BEN' => [ // Beni - Ciudades grandes primero
            'Trinidad', 'Riberalta', 'Guayaramerín', 'San Borja', 'Rurrenabaque', 'Reyes',
            'San Ignacio', 'Baures', 'Exaltación', 'Huacaraje', 'Loreto', 'Magdalena',
            'Puerto Siles', 'San Andrés', 'San Javier', 'San Joaquín', 'San Ramón', 'Santa Ana',
            'Santa Rosa'
        ],
        'PND' => [ // Pando - Ciudades grandes primero
            'Cobija', 'Porvenir', 'Puerto Gonzalo Moreno', 'Bella Flor', 'Bolpebra', 'Filadelfia',
            'Puerto Rico', 'San Lorenzo', 'San Pedro', 'Santa Rosa del Abuná', 'Santos Mercado', 'Sena',
            'Villa Nueva'
        ]
    ];
}

/**
 * Obtener todos los municipios con sus códigos únicos
 */
function obtenerTodosMunicipiosConCodigos() {
    $municipiosPorDept = obtenerMunicipiosPorDepartamento();
    $resultado = [];
    
    foreach ($municipiosPorDept as $codigoDept => $municipios) {
        $contador = 1;
        foreach ($municipios as $municipio) {
            $codigo = $codigoDept . '-' . str_pad($contador, 3, '0', STR_PAD_LEFT);
            $resultado[$codigo] = [
                'codigo' => $codigo,
                'nombre' => $municipio,
                'departamento' => $codigoDept
            ];
            $contador++;
        }
    }
    
    return $resultado;
}

/**
 * Obtener municipios de un departamento específico
 */
function obtenerMunicipiosDeDepartamento($codigoDepartamento) {
    $municipiosPorDept = obtenerMunicipiosPorDepartamento();

    if (!isset($municipiosPorDept[$codigoDepartamento])) {
        return [];
    }

    $municipios = $municipiosPorDept[$codigoDepartamento];
    // NO ordenar alfabéticamente - mantener orden personalizado (ciudades grandes primero)

    $resultado = [];
    $contador = 1;
    foreach ($municipios as $municipio) {
        $codigo = $codigoDepartamento . '-' . str_pad($contador, 3, '0', STR_PAD_LEFT);
        $resultado[] = [
            'codigo' => $codigo,
            'nombre' => $municipio
        ];
        $contador++;
    }
    
    return $resultado;
}