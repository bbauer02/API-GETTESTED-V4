const fs = require('fs');
const path = require('path');
const DataTypes = require('sequelize');
const bcrypt = require('bcrypt');
const _colors = require('colors');
const filebasename = path.basename(__filename);

// NOTE: Pour utiliser ce fichier, installer les dépendances:
// npm install cli-progress @faker-js/faker

// Import des packages
const cliProgress = require('cli-progress');
const { faker } = require('@faker-js/faker');

// On charge les jeux de données JSON Standards
const countries = require('../db/mock-countries');
const languages = require('../db/mock-languages');
const roles = require('../db/mock-roles');
const levels = require('../db/mock-levels');
const tests = require('../db/mock-tests');
const users = require('../db/mock-users');
const instituts = require('../db/mock-instituts');
const itemsCsv = require('../db/mock-items_csv');
const skills = require('../db/mock-skills');
const questions = require('../db/mock-questions');
const {subjects, subjectHasQuestions} = require('../db/mock-subjects');
const examHasSkills = require('../db/mock-exams_has_skill');
const { format } = require('date-fns');

/**
 * Classe améliorée pour la génération de données de test
 *
 * Améliorations:
 * - Barre de progression visuelle
 * - Configuration via fichier .mockrc.json
 * - Statistiques de génération
 * - Presets (minimal, standard, large, production-seed)
 * - Gestion d'erreurs améliorée
 * - Support de @faker-js/faker (moderne)
 */
class MockDatasImproved {

    // Params
    nbrUsers;
    nbrSessions;
    nbrSessionUsers;
    nbrSkills;
    nbrSubSkills;
    nbrSubSubSkills;
    nbrExams;
    nbrInstitutExamPrices;
    nbrInvoices;

    // Options
    options = {
        progressBar: true,
        verboseMode: false,
        showStatistics: true,
        colorOutput: true
    };

    // Statistiques
    stats = {
        startTime: null,
        endTime: null,
        duration: 0,
        itemsGenerated: {}
    };

    // Progress bar
    progressBar = null;

    // Datas
    countries = [];
    languages = [];
    roles = [];
    tests = [];
    levels = [];
    defaultAdminProd = {};
    itemsCsv = [];
    instituts = [];
    defaultUsers = [];
    randomUsers = [];
    institutHasDefaultUsers = [];
    institutHasRandomUsers = [];
    sessions = [];
    sessionHasUsers = [];
    skills = [];
    exams = [];
    sessionHasExams = [];
    empowerments = [];
    sessionExamsExaminators = [];
    institutHasPrices = [];
    sessionUserOption = [];
    invoices = [];
    invoice_lines = [];
    questions = [];
    questionSkills = [];
    subjects = [];
    subjectHasQuestions = [];
    examHasSkills = [];

    /**
     * Constructeur avec support de configuration
     * @param {Object} config - Configuration manuelle OU preset name
     */
    constructor(config = {}) {
        // Si config est une string, charger le preset
        if (typeof config === 'string') {
            config = this.loadPreset(config);
        }
        // Si pas de config, charger depuis .mockrc.json
        else if (Object.keys(config).length === 0) {
            config = this.loadConfigFile();
        }

        // Appliquer la configuration
        this.nbrUsers = config.users?.random || 500;
        this.nbrSessions = config.sessions?.count || 50;
        this.nbrSessionUsers = config.sessionUsers?.count || 1000;
        this.nbrSkills = config.skills?.main || 20;
        this.nbrSubSkills = config.skills?.sub || 20;
        this.nbrSubSubSkills = config.skills?.subSub || 20;
        this.nbrExams = config.exams?.count || 50;
        this.nbrInstitutExamPrices = config.prices?.institutExamPrices || 50;
        this.nbrInvoices = config.invoices?.count || 50;

        // Options
        if (config.options) {
            this.options = { ...this.options, ...config.options };
        }

        // Initialiser la progress bar si disponible et activée
        if (cliProgress && this.options.progressBar) {
            this.progressBar = new cliProgress.SingleBar({
                format: _colors.cyan('{bar}') + ' | {percentage}% | {value}/{total} | {stage}',
                barCompleteChar: '\u2588',
                barIncompleteChar: '\u2591',
                hideCursor: true
            });
        }
    }

    /**
     * Charge la configuration depuis .mockrc.json
     */
    loadConfigFile() {
        try {
            const configPath = path.join(__dirname, '../../.mockrc.json');
            if (fs.existsSync(configPath)) {
                const configFile = JSON.parse(fs.readFileSync(configPath, 'utf8'));
                return configFile.generation || {};
            }
        } catch (error) {
            this.log('⚠️  Impossible de charger .mockrc.json, utilisation des valeurs par défaut', 'yellow');
        }
        return {};
    }

    /**
     * Charge un preset depuis .mockrc.json
     */
    loadPreset(presetName) {
        try {
            const configPath = path.join(__dirname, '../../.mockrc.json');
            if (fs.existsSync(configPath)) {
                const configFile = JSON.parse(fs.readFileSync(configPath, 'utf8'));
                if (configFile.presets && configFile.presets[presetName]) {
                    this.log(`📦 Chargement du preset: ${presetName}`, 'cyan');
                    return configFile.presets[presetName];
                } else {
                    this.log(`⚠️  Preset "${presetName}" introuvable`, 'yellow');
                }
            }
        } catch (error) {
            this.log(`⚠️  Erreur chargement preset: ${error.message}`, 'yellow');
        }
        return {};
    }

    /**
     * Logger amélioré avec couleurs
     */
    log(message, color = 'white') {
        if (this.options.verboseMode || !this.options.colorOutput) {
            console.log(this.options.colorOutput ? _colors[color](message) : message);
        }
    }

    /**
     * Mise à jour de la progress bar
     */
    updateProgress(current, total, stage) {
        if (this.progressBar && this.options.progressBar) {
            this.progressBar.update(current, { total, stage });
        }
    }

    /**
     * Initialisation avec progress bar
     */
    async initialize() {
        this.stats.startTime = Date.now();

        try {
            console.log(_colors.cyan.bold('\n🚀 Génération des données de test...\n'));

            // Afficher la configuration
            if (this.options.verboseMode) {
                console.log(_colors.gray('Configuration:'));
                console.log(_colors.gray(`  - Utilisateurs: ${this.nbrUsers}`));
                console.log(_colors.gray(`  - Sessions: ${this.nbrSessions}`));
                console.log(_colors.gray(`  - Session-Users: ${this.nbrSessionUsers}`));
                console.log(_colors.gray(`  - Examens: ${this.nbrExams}`));
                console.log(_colors.gray(`  - Factures: ${this.nbrInvoices}\n`));
            }

            const totalSteps = 14; // Nombre d'étapes de génération
            let currentStep = 0;

            if (this.progressBar && this.options.progressBar) {
                this.progressBar.start(totalSteps, 0, { stage: 'Démarrage...' });
            }

            // Génération des données
            this.#fillCountries();
            this.updateProgress(++currentStep, totalSteps, 'Pays');

            this.#fillLanguages();
            this.updateProgress(++currentStep, totalSteps, 'Langues');

            this.#fillRoles();
            this.updateProgress(++currentStep, totalSteps, 'Rôles');

            this.#fillTests();
            this.updateProgress(++currentStep, totalSteps, 'Tests');

            this.#fillLevels();
            this.updateProgress(++currentStep, totalSteps, 'Niveaux');

            this.#fillItemsCSV();
            this.updateProgress(++currentStep, totalSteps, 'Items CSV');

            this.#fillInstituts();
            this.updateProgress(++currentStep, totalSteps, 'Instituts');

            this.#createDefaultAdminProd();
            await this.#fillDefaultUsers();
            this.updateProgress(++currentStep, totalSteps, 'Utilisateurs par défaut');

            await this.#fillRandomUsers();
            this.updateProgress(++currentStep, totalSteps, `Utilisateurs aléatoires (${this.nbrUsers})`);

            this.#fillInstitutHasDefaultUsers();
            this.#fillInstitutHasRandomUsers();
            this.updateProgress(++currentStep, totalSteps, 'Associations Institut-User');

            this.#fillSessions();
            this.updateProgress(++currentStep, totalSteps, `Sessions (${this.nbrSessions})`);

            this.#fillSessionsHasUsers();
            this.updateProgress(++currentStep, totalSteps, 'Inscriptions sessions');

            this.#fillSkills();
            this.#fillExams();
            this.#fillSessionHasExams();
            this.updateProgress(++currentStep, totalSteps, 'Examens & Compétences');

            this.#fillEmpowerments();
            this.#fillSessionExamExaminators();
            this.#fillInstitutHasPrices();
            this.#fillInvoices();
            this.#fillQuestions();
            this.#fillSubjects();
            this.#fillExamHasSkills();
            this.updateProgress(++currentStep, totalSteps, 'Données complémentaires');

            if (this.progressBar && this.options.progressBar) {
                this.progressBar.stop();
            }

            this.stats.endTime = Date.now();
            this.stats.duration = (this.stats.endTime - this.stats.startTime) / 1000;

            // Calculer les statistiques
            this.calculateStats();

            // Afficher les statistiques
            if (this.options.showStatistics) {
                this.displayStatistics();
            }

            console.log(_colors.green.bold('\n✅ Génération terminée avec succès!\n'));

        } catch (error) {
            if (this.progressBar && this.options.progressBar) {
                this.progressBar.stop();
            }
            console.error(_colors.red.bold('\n❌ Erreur lors de la génération:'), error);
            throw error;
        }
    }

    /**
     * Calcul des statistiques
     */
    calculateStats() {
        this.stats.itemsGenerated = {
            'Pays': this.countries.length,
            'Langues': this.languages.length,
            'Rôles': this.roles.length,
            'Tests': this.tests.length,
            'Niveaux': this.levels.length,
            'Instituts': this.instituts.length,
            'Utilisateurs': this.defaultUsers.length + this.randomUsers.length,
            'Sessions': this.sessions.length,
            'Inscriptions': this.sessionHasUsers.length,
            'Compétences': this.skills.length,
            'Examens': this.exams.length,
            'Questions': this.questions.length,
            'Sujets': this.subjects.length,
            'Factures': this.invoices.length,
            'Lignes facture': this.invoice_lines.length
        };

        this.stats.totalItems = Object.values(this.stats.itemsGenerated).reduce((a, b) => a + b, 0);
    }

    /**
     * Affichage des statistiques
     */
    displayStatistics() {
        console.log(_colors.cyan.bold('\n📊 Statistiques de génération:\n'));
        console.log(_colors.gray('═'.repeat(50)));

        Object.entries(this.stats.itemsGenerated).forEach(([key, value]) => {
            const padding = ' '.repeat(25 - key.length);
            console.log(_colors.white(`  ${key}${padding}`) + _colors.green.bold(`${value.toLocaleString()}`));
        });

        console.log(_colors.gray('═'.repeat(50)));
        console.log(_colors.cyan.bold(`  Total${' '.repeat(19)}`) + _colors.yellow.bold(`${this.stats.totalItems.toLocaleString()}`));
        console.log(_colors.gray('═'.repeat(50)));
        console.log(_colors.magenta(`\n⏱️  Durée: ${this.stats.duration.toFixed(2)}s`));
        console.log(_colors.magenta(`⚡ Performance: ${(this.stats.totalItems / this.stats.duration).toFixed(0)} items/s\n`));
    }

    // ==========================================
    // MÉTHODES DE REMPLISSAGE (identiques)
    // ==========================================

    #fillExamHasSkills() {
        // Récupérer les IDs des examens qui existent réellement
        const existingExamIds = this.exams.map(exam => exam.exam_id);

        // Filtrer uniquement les exam_has_skills dont l'exam_id existe
        for(const examHasSkill of examHasSkills) {
            if (existingExamIds.includes(examHasSkill.exam_id)) {
                this.examHasSkills.push({
                    exam_id: examHasSkill.exam_id,
                    skill_id: examHasSkill.skill_id
                });
            }
        }
    }

    #fillSubjects() {
        for(const subject of subjects) {
            this.subjects.push({
                subject_id: subject.subject_id,
                title: subject.title,
                description: subject.description,
                test_id: subject.test_id,
                level_id: subject.level_id,
            });
        }

        for(const subjectHasQuestion of subjectHasQuestions) {
            this.subjectHasQuestions.push({
                subject_id: subjectHasQuestion.subject_id,
                question_id: subjectHasQuestion.question_id
            });
        }
    }

    #fillQuestions() {
        for( const question of questions) {
            this.questions.push({
                question_id: question.question_id,
                label: question.label,
                test_id:question.test_id,
                level_id: question.level_id,
                instruction: question.instruction,
                duration: question.duration,
                points: question.points,
                question_data: question.question_data
            });

            for(const skill_id of question.skills)
            this.questionSkills.push({
                question_id: question.question_id,
                skill_id: skill_id
            });
        }
    }

    #fillCountries() {
        for (const country of countries) {
            this.countries.push({
                label: country.en_short_name,
                countryNationality: country.nationality,
                countryLanguage: country.nationality,
                code: country.alpha_2_code
            });
        }
    }

    #fillLanguages() {
        for (const language of languages) {
            this.languages.push({
                nativeName: language.nativeName,
                name: language.name
            });
        }
    }

    #fillRoles() {
        for (const role of roles) {
            this.roles.push({
                label: role.label,
                power: role.power
            });
        }
    }

    #fillTests() {
        let testid = 1;
        for (const test of tests) {
            this.tests.push({
                test_id : testid,
                label: test.label,
                isInternal: test.isInternal,
                parent_id: test.parent_id,
                owner_id: test.owner_id
            });
            testid++;
        }
    }

    #fillLevels() {
        let levelId=1;
        for (const level of levels) {
            this.levels.push({
                level_id: levelId,
                label: level.label,
                ref: level.ref,
                description: level.description,
                test_id: level.test_id
            });
            levelId++;
        }
    }

    async #createDefaultAdminProd() {
        const BCRYPT_ROUNDS = parseInt(process.env.BCRYPT_ROUNDS) || 12;
        this.defaultAdminProd = {
            login: "admin",
            password: await bcrypt.hash('admin', BCRYPT_ROUNDS),
            email: "contact@get-skills.online",
            phone: "",
            gender: 1,
            civility: 1,
            firstname: "Admin",
            lastname: "Get-skills",
            adress1: "place de l'église",
            adress2: "",
            zipcode: "02000",
            city: "URCEL",
            country_id: 76,
            nativeCountry_id: 76,
            birthday: new Date(),
            nationality_id: 76,
            firstlanguage_id: 76,
            systemRole_id: 5
        }
    }

    #fillItemsCSV() {
        for (const item of itemsCsv) {
            this.itemsCsv.push({
                csvItem_id : item.csvItem_id,
                field: item.field,
                label: item.label,
                inLine: item.inLine,
                test_id: item.test_id
            });
        }
    }

    #fillInstituts() {
        for (const institut of instituts) {
            this.instituts.push({
                institut_id : institut.institut_id,
                label: institut.label,
                adress1: institut.adress1,
                adress2: institut.adress2,
                zipcode: institut.zipcode,
                city: institut.city,
                country_id: institut.country_id,
                email: institut.email,
                siteweb: institut.siteweb,
                phone: institut.phone,
                socialNetwork: institut.socialNetwork,
                stripeId: institut.stripeId,
                stripeActivated: institut.stripeActivated
            });
        }
    }

    async #fillDefaultUsers() {
        const BCRYPT_ROUNDS = parseInt(process.env.BCRYPT_ROUNDS) || 12;
        for (const user of users) {
            this.defaultUsers.push({
                user_id: user.user_id,
                login: user.login,
                password: await bcrypt.hash(user.password, BCRYPT_ROUNDS),
                email: user.email,
                phone: user.phone,
                gender: user.gender,
                civility: user.civility,
                firstname: user.firstname,
                lastname: user.lastname,
                adress1: user.adress1,
                adress2: user.adress2,
                zipcode: user.zipcode,
                city: user.city,
                country_id: user.country_id,
                nativeCountry_id: user.country_id,
                birthday: user.birthday,
                nationality_id: user.nationality_id,
                firstlanguage_id: user.firstlanguage_id,
                systemRole_id: user.systemRole_id
            });
        }
    }

    async #fillRandomUsers(){
        const BCRYPT_ROUNDS = parseInt(process.env.BCRYPT_ROUNDS) || 12;
        for (let index = 1; index <= this.nbrUsers; index++) {
            this.randomUsers.push({
                user_id: this.defaultUsers.length + index,
                login: "user"+index,
                password: await bcrypt.hash('123', BCRYPT_ROUNDS),
                email: faker.internet.email(),
                phone: faker.phone.number(),
                gender: faker.number.int({ min: 1, max: 2 }),
                civility: faker.number.int({ min: 1, max: 2 }),
                firstname: faker.person.firstName(),
                lastname: faker.person.lastName(),
                adress1: faker.location.streetAddress(),
                adress2: faker.location.secondaryAddress(),
                zipcode: faker.location.zipCode(),
                city: faker.location.city(),
                country_id: faker.number.int({ min: 1, max: countries.length }),
                nativeCountry_id: faker.number.int({ min: 1, max: countries.length }),
                birthday: faker.date.between({ from: '1950-01-01', to: '2002-12-31' }),
                nationality_id: faker.number.int({ min: 1, max: countries.length }),
                firstlanguage_id: faker.number.int({ min: 1, max: languages.length }),
                systemRole_id: 1
            });
        }
    }

    #fillInstitutHasDefaultUsers() {
        this.institutHasDefaultUsers = [
            { 'user_id': 1, 'institut_id': 2, 'role_id': 1 },
            { 'user_id': 1, 'institut_id': 1, 'role_id': 1 },
            { 'user_id': 2, 'institut_id': 1, 'role_id': 4 },
            { 'user_id': 3, 'institut_id': 2, 'role_id': 1 },
            { 'user_id': 4, 'institut_id': 2, 'role_id': 2 }
        ]
    }

    #fillInstitutHasRandomUsers() {
        for (let index = 1; index <= this.nbrUsers; index++) {
            const obj = {
                'user_id': this.defaultUsers.length + index,
                'institut_id': faker.number.int({ min: 1, max: instituts.length }),
                'role_id': faker.number.int({ min: 1, max: 4 })
            }
            this.institutHasRandomUsers.push(obj);
        }
    }

    #fillSessions() {
        for (let index = 1; index < this.nbrSessions; index++) {
            const dateStart = faker.date.future();
            const dateEnd = faker.date.future({ refDate: dateStart });
            const dateLimite = faker.date.past({ refDate: dateStart });
            const testId = faker.number.int({ min: 1, max: 5 });
            let levelId= null;
            switch(testId) {
                case 1 :
                    levelId= faker.number.int({ min: 1, max: 5 });
                break;
                case 2 :
                    levelId= faker.number.int({ min: 13, max: 14 });
                break;
                case 3 :
                    levelId= faker.number.int({ min: 6, max: 10 });
                break;
                case 4 :
                    levelId= null
                break;
                case 5 :
                    levelId= faker.number.int({ min: 11, max: 12 });
                break;
            }
            this.sessions.push({
                session_id:index,
                institut_id: faker.number.int({ min: 1, max: this.instituts.length }),
                start: dateStart,
                end: dateEnd,
                limitDateSubscribe:dateLimite,
                placeAvailable: faker.number.int({ min: 1, max: 100 }),
                validation: faker.number.int({ min: 0, max: 1 }),
                test_id: testId,
                level_id: levelId
            });
        }
    }

    #fillSessionsHasUsers() {
        for (let index = 1; index <= this.nbrSessionUsers; index++) {
            const currentSession = faker.helpers.arrayElement(this.sessions);
            const {institut_id, placeAvailable, session_id} = currentSession;
            const usersInInstitut = this.institutHasRandomUsers.filter(u => u.institut_id === institut_id);

            if (usersInInstitut.length === 0) continue;

            const ranUser_id = faker.helpers.arrayElement(usersInInstitut).user_id;
            const isSubscribed = this.sessionHasUsers.find(s => s.user_id === ranUser_id && s.session_id === session_id);
            const isSessionFull = this.sessionHasUsers.filter(s => s.session_id === session_id).length >= placeAvailable;

            if(!isSessionFull && isSubscribed === undefined) {
                this.sessionHasUsers.push({
                    sessionUser_id : index,
                    session_id,
                    user_id: ranUser_id,
                    paymentMode: 1,
                    numInscrAnt: null,
                    hasPaid: faker.number.int({ min: 0, max: 1 }),
                    informations: faker.lorem.sentence()
                });
            }
        }
    }

    #fillSkills() {
        for (const skill of skills) {
            this.skills.push({
                skill_id : skill.skill_id,
                label: skill.label,
                parent_id: skill.parent_id,
                test_id: skill.test_id
            });
        }
    }

    #fillExams() {
        for (let examId = 1;examId <= this.nbrExams;examId++) {
            const randomTest = faker.helpers.arrayElement(this.tests);
            const listLevelTest = this.levels.filter(l => l.test_id === randomTest.test_id);
            const randomLevel = listLevelTest.length > 0 ? faker.helpers.arrayElement(listLevelTest) : null;

            this.exams.push({
                exam_id: examId,
                test_id: randomTest? randomTest.test_id : null,
                level_id: randomLevel? randomLevel.level_id : null,
                label: "Epreuve N°" + examId,
                isWritten: faker.number.int({ min: 0, max: 1 }),
                isOption: faker.number.int({ min: 0, max: 1 }),
                price: faker.number.int({ min: 10, max: 800 }),
                coeff: faker.number.int({ min: 1, max: 3 }),
                nbrQuestions: faker.number.int({ min: 1, max: 20 }),
                duration: faker.number.int({ min: 10, max: 3600 }),
                successScore: faker.number.int({ min: 100, max: 500 })
            });
        }
    }

    #fillSessionHasExams() {
        let sessionHasExamId = 1;
        for(const session of this.sessions) {
            const sessionExams = this.exams.filter(e => e.test_id === session.test_id && e.level_id === session.level_id);
            for(const exam of sessionExams) {
                this.sessionHasExams.push({
                    sessionHasExam_id : sessionHasExamId,
                    adressExam: faker.location.streetAddress() + " " + faker.location.zipCode() + " " + faker.location.city(),
                    DateTime: faker.date.future(),
                    session_id: session.session_id,
                    exam_id: exam.exam_id,
                    room: "Room " + faker.number.int({ min: 1, max: 20 }),
                });
                sessionHasExamId++;
            }
        }
    }

    #fillEmpowerments() {
        let empowermentId = 1;
        for(const institut of this.instituts) {
            const teachers = this.institutHasRandomUsers.filter(u => u.institut_id === institut.institut_id && u.role_id >= 2);
            for(const teacher of teachers) {
                this.empowerments.push({
                    empowermentTest_id: empowermentId,
                    code: faker.string.alphanumeric(6).toUpperCase(),
                    institut_id: institut.institut_id,
                    user_id: teacher.user_id,
                    test_id: faker.number.int({ min: 1, max: this.tests.length })
                });
                empowermentId++;
            }
        }
    }

    #fillSessionExamExaminators() {
        let sessionExamHasExaminator_id = 1;
        for(const sessionUser of this.sessionHasUsers) {
            const sessionExams = this.sessionHasExams.filter(e => e.session_id === sessionUser.session_id);
            const session = this.sessions.find(s => s.session_id === sessionUser.session_id);
            const empowerment_test = this.empowerments.filter(e => e.institut_id === session.institut_id && e.test_id === session.test_id);

            for(const sessionExam of sessionExams) {
                const isExist = this.sessionExamsExaminators.find(e => e.sessionHasExam_id === sessionExam.sessionHasExam_id && e.sessionUser_id === sessionUser.sessionUser_id);
                if(!isExist) {
                    let empowermentTestId = faker.number.int({ min: 0, max: empowerment_test.length });
                    if(empowermentTestId===0) empowermentTestId = null;
                    this.sessionExamsExaminators.push({
                        sessionExamHasExaminator_id: sessionExamHasExaminator_id,
                        sessionHasExam_id: sessionExam.sessionHasExam_id,
                        sessionUser_id: sessionUser.sessionUser_id,
                        empowermentTest_id: empowermentTestId
                    });
                    sessionExamHasExaminator_id++;
                }
            }
        }
    }

    #fillInstitutHasPrices() {
        for (let index = 1; index <= this.nbrInstitutExamPrices ; index++) {
            const institut = faker.helpers.arrayElement(this.instituts);
            const exam = faker.helpers.arrayElement(this.exams);
            const isExist = this.institutHasPrices.find(p => p.institut_id === institut.institut_id && p.exam_id === exam.exam_id);
            if(!isExist) {
                this.institutHasPrices.push({
                    price_id:index,
                    institut_id: institut.institut_id,
                    exam_id: exam.exam_id,
                    price: faker.number.int({ min: 200, max: 1000 }),
                    tva:22
                });
            }
        }
    }

    #fillSessionUserOption() {
        for(const sessionUser of this.sessionHasUsers) {
            const sessionExams = this.sessionHasExams.filter(e => e.session_id === sessionUser.session_id);
            for(const ExamSession of sessionExams) {
                let tva = null;
                let userPrice = faker.number.int({ min: 0, max: 3 });
                if(userPrice < 3) {
                    userPrice = null;
                }
                else {
                    userPrice = faker.number.int({ min: 200, max: 1000 });
                    tva = faker.number.int({ min: 10, max: 22 });
                }
                if(!this.sessionUserOption.find(o => o.sessionUser_id === sessionUser.sessionUser_id && o.exam_id === ExamSession.exam_id)) {
                    const adress = faker.location.streetAddress() + " " + faker.location.zipCode() + " " + faker.location.city();
                    const exam = this.exams.find(e => e.exam_id === ExamSession.exam_id);
                    const isCandidate = exam ? !exam.isOption : false;

                    this.sessionUserOption.push({
                        exam_id: ExamSession.exam_id,
                        user_price: userPrice,
                        addressExam: adress,
                        tva: tva,
                        DateTime: faker.date.future(),
                        isCandidate: isCandidate,
                        sessionUser_id: sessionUser.sessionUser_id
                    });
                }
            }
        }
    }

    padWithZeros(number) {
        const numberString = number.toString();
        const zerosToAdd = 5 - numberString.length;

        if (zerosToAdd <= 0) {
          return numberString;
        } else {
          const paddedNumber = '0'.repeat(zerosToAdd) + numberString;
          return paddedNumber;
        }
    }

    #fillInvoices() {
        for (let index = 1; index <= this.nbrInvoices; index++) {
            const session = faker.helpers.arrayElement(this.sessions);
            const user = faker.helpers.arrayElement(this.defaultUsers);
            const institut = faker.helpers.arrayElement(this.instituts);
            const test = faker.helpers.arrayElement(this.tests);
            const level = faker.helpers.arrayElement(this.levels);

            const invoice = {
                "session" : "Session du " + format(faker.date.future(), 'dd/MM/yyyy') + " au " + format(faker.date.future(), 'dd/MM/yyyy'),
                "session_id" : session.session_id,
                "user_id": user.user_id,
                "institut_id" : institut.institut_id,
                "customerFirstname" : faker.person.firstName(),
                "customerLastname" : faker.person.lastName(),
                "customerAddress1" : faker.location.streetAddress(),
                "customerAddress2" : faker.location.streetAddress(),
                "customerCity" : faker.location.city(),
                "customerZipCode" : faker.location.zipCode(),
                "customerCountry" : faker.location.country(),
                "customerEmail" : faker.internet.email(),
                "customerPhone" : faker.phone.number(),
                "status" : faker.number.int({ min: 0, max: 3 }),
                "ref_client" : faker.string.alphanumeric(6).toUpperCase(),
                "ref_invoice": `F${this.padWithZeros(index)}`,
                "test" : test.label,
                "level" : level.label,
                "createDate" : faker.date.past(),
                "dueDate" : faker.date.future()
            }

            const nbrLines = faker.number.int({ min: 1, max: 5 });
            for (let index2 = 1; index2 <= nbrLines; index2++) {
                this.invoice_lines.push({
                    "invoice_id" : index,
                    "label" : "EXAM " + faker.number.int({ min: 1, max: 10 }),
                    "price_HT" : faker.number.int({ min: 100, max: 1000 }),
                    "tva" : faker.number.int({ min: 10, max: 22 }),
                    "exam_id" : faker.number.int({ min: 1, max: 10 }),
                });
            }

            this.invoices.push(invoice);
        }
    }
}

module.exports = {
    MockDatas: MockDatasImproved,
    MockDatasImproved
};
