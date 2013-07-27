# encoding: UTF-8

namespace :cantonbe do
  namespace :budget do

    desc "generate json"
    task :generate => [:generate_json_from_csvs, :enrich_json]

    desc "creates canton bern json file"
    task :generate_json_from_csvs do
      puts "start processing"
      budget = OpenBudget::Budget.new

      budget.load_meta 'data/be/meta.json'
      budget.load_cantonbe_csv 'source/cantonbe/Kanton BE_Produkgruppen_DB IV nach DIR_2011.csv'
      budget.load_cantonbe_csv 'source/cantonbe/Kanton BE_Produkgruppen_DB IV nach DIR_2012_Werte in 1000.csv', exponent: 3, comma: :clear

      budget.save_pretty_json 'data/be/data.json'
      puts "done"
    end

    desc "download canton bern org meta data"
    task :download_directorate_meta do
      overview = Nokogiri::HTML(open('http://www.be.ch/portal/de/behoerden/verwaltung.html'))

      directorates = []
      overview.css('.departement-index div').each do |directorate|
        h3 = directorate.css('h3 a')[0]
        next unless h3

        name = h3.content.match(/(?<name>.+)\s*\((?<acronym>.+)\)/)

        img = directorate.css('img')[0]
        director = directorate.css('ul li a')[0]

        directorates << {
          name: name[:name].strip.gsub(/\s+/, ' '),
          acronym: name[:acronym],
          website: h3[:href],
          director: {
            name: img[:alt],
            image_url: img[:src],
            website: director[:href]
          }
        }
      end

      File.open('source/cantonbe/directorate_meta.json', 'wb') do |file|
        file.write JSON.pretty_generate JSON.parse(directorates.to_json)
      end
    end

    desc "enrich data json"
    task :enrich_json do
      budget = OpenBudget::Budget.from_file('data/be/data.json')
      directorate_meta = JSON.parse File.read('source/cantonbe/directorate_meta.json')

      directorate_meta.each do |directorate|
        id_path = budget.cantonbe_names_to_ids [
          directorate['name']
        ]
        node = budget.get_node(id_path)
        if node
          p "found #{directorate['name']}"
          node.short_name = directorate['acronym']
        else
          p "not found #{directorate['name']} #{id_path}"
        end
      end

      budget.save_pretty_json 'data/be/data.json'
    end

  end
end
