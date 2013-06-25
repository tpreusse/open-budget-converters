# encoding: UTF-8

require 'csv'
require 'json'
require 'active_support/all'

require './lib/open_budget'

namespace :finstabe do
  namespace :bfh do
    namespace :generate_json do
      desc "creates funktionale gliederung json files (optionally pass bfs id, defaults to all)"
      task :funktionale_gliederung, [:bfs_no] do |t, args|
        args.with_defaults(:bfs_no => '*')
        files = Dir.glob("source/finstabe/bfh/funktionale_gliederung/FINSTA_NachFunktionalerGliederung_#{args.bfs_no}.csv")
        puts "start processing #{files.length} files (bfs no: #{args.bfs_no})"
        files.each do |file_path|
          budget = OpenBudget::Budget.new

          budget.from_finstabe_bfh_csv file_path
          # p nodes.nodes.to_s
          if budget.meta.present? && budget.meta[:bfs_no].present?
            File.open("data/finstabe/funktionale_gliederung/#{budget.meta[:bfs_no]}.json", 'wb') do |file|
              file.write budget.to_json
            end
            puts "#{budget.meta[:bfs_no]} done"
          else
            puts "#{file_path} meta missing"
          end
        end
      end
    end
  end
end

namespace :zurich do
  desc "creates zurich json file"
  task :generate_json_from_csv do
    puts "start processing"
    budget = OpenBudget::Budget.new

    budget.load_meta 'data/zurich/meta.json'
    budget.from_zurich_csv 'source/zurich/B13_Institution_Konzernkonto.csv'

    File.open("data/zurich/data.json", 'wb') do |file|
      file.write budget.to_json
    end
    puts "done"
  end
end

namespace :cantonbe do
  desc "creates canton bern json file"
  task :generate_json_from_csvs do
    puts "start processing"
    budget = OpenBudget::Budget.new

    budget.load_meta 'data/cantonbe/meta.json'
    budget.load_cantonbe_csv 'source/cantonbe/Kanton BE_Produkgruppen_DB IV nach DIR_2011.csv'
    budget.load_cantonbe_csv 'source/cantonbe/Kanton BE_Produkgruppen_DB IV nach DIR_2012.csv'

    File.open("data/cantonbe/data.json", 'wb') do |file|
      file.write budget.to_json
    end
    puts "done"
  end
end
